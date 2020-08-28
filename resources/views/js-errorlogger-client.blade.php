<script defer>
  class ErrorLoggerClient {
    /**
     * Initialize ErrorLoggerClient instance.
     */
    constructor () {
      if (typeof window !== 'undefined') {
        window.addEventListener('error', async (event) => {
          await this.report(event);
        });
      }
    }

    /**
     * Reports an error to ErrorLogger API.
     *
     * @param {ErrorEvent} event
     *
     * @returns {Promise}
     */
    report (event) {
      return new Promise(async (resolve, reject) => {
        try {
          const response = await fetch(`${window.location}errorlogger-api/report`, {
            body: JSON.stringify(this.mapError(event)),
            method: 'POST',
            headers: {
              Accept: 'application/json',
              'Content-Type': 'application/json'
            }
          });
        } catch (err) {
          reject({
            status: err.status,
            statusText: err.statusText,
          });
        }
      });
    }

    /**
     * Maps an error before sending to API.
     *
     * @param {ErrorEvent} event
     *
     * @returns {Exception}
     */
    mapError (event) {
      const sdkVersion = '0.9.5';

      const stack = event.error.stack.toString();
      let exception = event.error.toString();

      if (stack) {
        exception += `\n ${stack}`;
      }

      return {
        message: event.message,
        exception: exception,
        file: event.filename,
        url: window.location.href,
        line: event.lineno,
        column: event.colno,
        error: event.message,
        stack: event.error.stack,
        type: 'frontend',
        sdkVersion: sdkVersion
      };
    }
  }

  new ErrorLoggerClient();
</script>
