const HTML_ERROR_PATTERN =
	/<(?:!doctype|html|body|p|a|div|br|h[1-6])(?:\s|>|\/)/i;

export const getApiErrorMessage = ( error, fallback ) => {
	const message =
		typeof error?.message === 'string' ? error.message.trim() : '';

	if ( ! message || HTML_ERROR_PATTERN.test( message ) ) {
		return fallback;
	}

	return message;
};
