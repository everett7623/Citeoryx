#!/usr/bin/env bash

set -euo pipefail

if [ "$#" -lt 3 ]; then
	echo "Usage: $0 <db-name> <db-user> <db-pass> [db-host] [wp-version] [skip-database-creation]" >&2
	exit 1
fi

DB_NAME="$1"
DB_USER="$2"
DB_PASS="$3"
DB_HOST="${4:-localhost}"
WP_VERSION="${5:-latest}"
SKIP_DB_CREATE="${6:-false}"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

TMPDIR="${TMPDIR:-/tmp}"
TMPDIR="${TMPDIR%/}"
WP_TESTS_DIR="${WP_TESTS_DIR:-${TMPDIR}/wordpress-tests-lib}"
WP_CORE_DIR="${WP_CORE_DIR:-${TMPDIR}/wordpress}"

download() {
	curl --fail --location --silent --show-error "$1" --output "$2"
}

resolve_wordpress_versions() {
	local versions_file="${TMPDIR}/wp-versions.json"
	download 'https://api.wordpress.org/core/version-check/1.7/' "$versions_file"
	php "${SCRIPT_DIR}/resolve-wp-test-versions.php" "$versions_file" "$WP_VERSION"
}

mapfile -t RESOLVED_WP_VERSIONS < <(resolve_wordpress_versions)
RESOLVED_WP_VERSION="${RESOLVED_WP_VERSIONS[0]:-}"
RESOLVED_WP_TESTS_VERSION="${RESOLVED_WP_VERSIONS[1]:-}"
if [ -z "$RESOLVED_WP_VERSION" ] || [ -z "$RESOLVED_WP_TESTS_VERSION" ]; then
	echo "Unable to resolve WordPress version: ${WP_VERSION}" >&2
	exit 1
fi

install_wordpress() {
	if [ -f "${WP_CORE_DIR}/wp-settings.php" ]; then
		return
	fi

	rm -rf "$WP_CORE_DIR"
	mkdir -p "$WP_CORE_DIR"
	download "https://wordpress.org/wordpress-${RESOLVED_WP_VERSION}.tar.gz" "${TMPDIR}/wordpress.tar.gz"
	tar --strip-components=1 -xzf "${TMPDIR}/wordpress.tar.gz" -C "$WP_CORE_DIR"
}

install_test_suite() {
	local archive="${TMPDIR}/wordpress-develop.tar.gz"
	local extracted="${TMPDIR}/wordpress-develop-${RESOLVED_WP_TESTS_VERSION}"

	if [ ! -f "${WP_TESTS_DIR}/includes/functions.php" ]; then
		rm -rf "$WP_TESTS_DIR" "$extracted"
		mkdir -p "$WP_TESTS_DIR"
		download "https://github.com/WordPress/wordpress-develop/archive/refs/tags/${RESOLVED_WP_TESTS_VERSION}.tar.gz" "$archive"
		tar -xzf "$archive" -C "$TMPDIR"
		mv "${extracted}/tests/phpunit/includes" "$WP_TESTS_DIR/"
		mv "${extracted}/tests/phpunit/data" "$WP_TESTS_DIR/"
		rm -rf "$extracted" "$archive"
	fi

	if [ ! -f "${WP_TESTS_DIR}/wp-tests-config.php" ]; then
		download "https://raw.githubusercontent.com/WordPress/wordpress-develop/${RESOLVED_WP_TESTS_VERSION}/wp-tests-config-sample.php" "${WP_TESTS_DIR}/wp-tests-config.php"
		sed -i "s|dirname( __FILE__ ) . '/src/'|'${WP_CORE_DIR}/'|" "${WP_TESTS_DIR}/wp-tests-config.php"
		sed -i "s|__DIR__ . '/src/'|'${WP_CORE_DIR}/'|" "${WP_TESTS_DIR}/wp-tests-config.php"
		sed -i "s/youremptytestdbnamehere/${DB_NAME}/" "${WP_TESTS_DIR}/wp-tests-config.php"
		sed -i "s/yourusernamehere/${DB_USER}/" "${WP_TESTS_DIR}/wp-tests-config.php"
		sed -i "s/yourpasswordhere/${DB_PASS}/" "${WP_TESTS_DIR}/wp-tests-config.php"
		sed -i "s|localhost|${DB_HOST}|" "${WP_TESTS_DIR}/wp-tests-config.php"
	fi
}

create_database() {
	if [ "$SKIP_DB_CREATE" = 'true' ]; then
		return
	fi

	local host="${DB_HOST%%:*}"
	local port="${DB_HOST##*:}"
	local connection=(--host="$host" --user="$DB_USER" --password="$DB_PASS" --protocol=tcp)
	if [ "$port" != "$host" ]; then
		connection+=(--port="$port")
	fi

	mysqladmin "${connection[@]}" create "$DB_NAME"
}

install_wordpress
install_test_suite
create_database

echo "WordPress ${RESOLVED_WP_VERSION} core with ${RESOLVED_WP_TESTS_VERSION} test suite installed."
