/**
 * Central API service. All HTTP calls should go through MBMS_API.
 * Pages must not use raw fetch() for backend requests.
 */
(function (global) {
  'use strict';

  function ApiError(message, details) {
    details = details || {};
    this.name = 'ApiError';
    this.message = message || 'Request failed.';
    this.status = details.status || 0;
    this.data = details.data != null ? details.data : null;
    this.errors = details.errors || null;
    this.isNetworkError = !!details.isNetworkError;
    this.isTimeout = !!details.isTimeout;
  }

  ApiError.prototype = Object.create(Error.prototype);
  ApiError.prototype.constructor = ApiError;

  function joinUrl(base, path) {
    if (!path) {
      return String(base || '');
    }
    if (/^https?:\/\//i.test(path)) {
      return path;
    }
    var cleanedBase = String(base || '').replace(/\/+$/, '');
    var cleanedPath = String(path).replace(/^\/+/, '');
    if (!cleanedBase) {
      return '/' + cleanedPath;
    }
    return cleanedBase + '/' + cleanedPath;
  }

  function buildQuery(params) {
    if (!params) {
      return '';
    }
    var search = new URLSearchParams();
    Object.keys(params).forEach(function (key) {
      var value = params[key];
      if (value === undefined || value === null || value === '') {
        return;
      }
      if (Array.isArray(value)) {
        value.forEach(function (item) {
          search.append(key, item);
        });
        return;
      }
      search.append(key, value);
    });
    var query = search.toString();
    return query ? '?' + query : '';
  }

  function parseBody(response) {
    var contentType = response.headers.get('content-type') || '';
    if (response.status === 204) {
      return Promise.resolve(null);
    }
    if (contentType.indexOf('application/json') !== -1) {
      return response.json();
    }
    return response.text();
  }

  function laravelMessage(payload, fallback) {
    if (!payload || typeof payload !== 'object') {
      return fallback;
    }
    if (typeof payload.message === 'string' && payload.message) {
      return payload.message;
    }
    if (payload.errors && typeof payload.errors === 'object') {
      var firstKey = Object.keys(payload.errors)[0];
      var first = firstKey ? payload.errors[firstKey] : null;
      if (Array.isArray(first) && first[0]) {
        return first[0];
      }
    }
    return fallback;
  }

  var inflightCount = 0;

  function request(method, path, options) {
    options = options || {};
    var config = global.MBMS_CONFIG || {};
    inflightCount += 1;
    var url = joinUrl(config.apiBaseUrl, path) + buildQuery(options.params);
    var headers = Object.assign(
      {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      },
      config.defaultHeaders || {},
      options.headers || {}
    );

    var token = config.getAuthToken ? config.getAuthToken() : '';
    if (token && !headers.Authorization) {
      headers.Authorization = 'Bearer ' + token;
    }

    var body = options.body;
    if (body instanceof FormData) {
      delete headers['Content-Type'];
    } else if (body != null && typeof body === 'object') {
      headers['Content-Type'] = headers['Content-Type'] || 'application/json';
      body = JSON.stringify(body);
    }

    var timeout = options.timeout != null ? options.timeout : config.timeout || 15000;
    var controller = options.signal ? null : new AbortController();
    var signal = options.signal || controller.signal;
    var timer = global.setTimeout(function () {
      if (controller) {
        controller.abort();
      }
    }, timeout);

    var verb = String(method || 'GET').toUpperCase();

    return fetch(url, {
      method: verb,
      headers: headers,
      body: verb === 'GET' || verb === 'HEAD' ? undefined : body,
      signal: signal,
      credentials: options.credentials || config.credentials || 'omit'
    })
      .then(function (response) {
        return parseBody(response).then(function (payload) {
          if (!response.ok) {
            throw new ApiError(laravelMessage(payload, 'Request failed (' + response.status + ').'), {
              status: response.status,
              data: payload,
              errors: payload && payload.errors ? payload.errors : null
            });
          }
          return {
            ok: true,
            status: response.status,
            data: payload,
            headers: response.headers
          };
        });
      })
      .catch(function (error) {
        if (error instanceof ApiError) {
          throw error;
        }
        var timedOut = error && error.name === 'AbortError';
        throw new ApiError(
          timedOut ? 'The request timed out.' : 'Unable to reach the server.',
          {
            status: 0,
            isNetworkError: true,
            isTimeout: timedOut,
            data: null
          }
        );
      })
      .finally(function () {
        inflightCount = Math.max(0, inflightCount - 1);
        global.clearTimeout(timer);
      });
  }

  function withBody(method, path, body, options) {
    options = options || {};
    options.body = body;
    return request(method, path, options);
  }

  global.MBMS_API = {
    ApiError: ApiError,
    request: request,
    getInflightCount: function () {
      return inflightCount;
    },
    get: function (path, options) {
      return global.MBMS_API.request('GET', path, options);
    },
    post: function (path, body, options) {
      options = options || {};
      options.body = body;
      return global.MBMS_API.request('POST', path, options);
    },
    put: function (path, body, options) {
      options = options || {};
      options.body = body;
      return global.MBMS_API.request('PUT', path, options);
    },
    patch: function (path, body, options) {
      options = options || {};
      options.body = body;
      return global.MBMS_API.request('PATCH', path, options);
    },
    delete: function (path, options) {
      return global.MBMS_API.request('DELETE', path, options);
    }
  };
})(window);
