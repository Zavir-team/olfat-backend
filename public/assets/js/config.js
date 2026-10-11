/**
 * Central frontend configuration.
 * Change the API base URL here, via localStorage, or MBMS_CONFIG.setApiBaseUrl().
 * Do not hard-code production URLs in page scripts.
 */
(function (global) {
  'use strict';

  var STORAGE_KEY = 'MBMS_API_BASE_URL';
  var TOKEN_KEY = 'MBMS_AUTH_TOKEN';
  var USER_KEY = 'MBMS_USER_ID';
  var LOCATION_KEY = 'MBMS_LOCATION_ID';
  var LOCATION_NAME_KEY = 'MBMS_LOCATION_NAME';
  var DEFAULT_API_BASE_URL = '/api/v1';

  function readStorage(key) {
    try {
      return global.localStorage.getItem(key) || '';
    } catch (err) {
      return '';
    }
  }

  function writeStorage(key, value) {
    try {
      if (value) {
        global.localStorage.setItem(key, value);
      } else {
        global.localStorage.removeItem(key);
      }
    } catch (err) {
      /* private mode / blocked storage */
    }
  }

  function normalizeBaseUrl(url) {
    return String(url || '').trim().replace(/\/+$/, '');
  }

  var storedUrl = normalizeBaseUrl(readStorage(STORAGE_KEY));
  if (storedUrl && storedUrl.indexOf('/api/v1') === -1) {
    storedUrl = '';
    writeStorage(STORAGE_KEY, '');
  }

  var config = {
    appName: 'Mobile Business Management System',
    apiBaseUrl: storedUrl || DEFAULT_API_BASE_URL,
    defaultApiBaseUrl: DEFAULT_API_BASE_URL,
    timeout: 15000,
    credentials: 'omit',
    defaultHeaders: {},

    setApiBaseUrl: function (url) {
      var next = normalizeBaseUrl(url);
      if (!next) {
        throw new Error('API base URL is required.');
      }
      this.apiBaseUrl = next;
      writeStorage(STORAGE_KEY, next);
      return this.apiBaseUrl;
    },

    resetApiBaseUrl: function () {
      this.apiBaseUrl = DEFAULT_API_BASE_URL;
      writeStorage(STORAGE_KEY, '');
      return this.apiBaseUrl;
    },

    getAuthToken: function () {
      return readStorage(TOKEN_KEY);
    },

    setAuthToken: function (token) {
      writeStorage(TOKEN_KEY, token || '');
    },

    clearAuthToken: function () {
      writeStorage(TOKEN_KEY, '');
      writeStorage(USER_KEY, '');
      writeStorage(LOCATION_KEY, '');
      writeStorage(LOCATION_NAME_KEY, '');
    },

    getUserId: function () {
      return readStorage(USER_KEY);
    },

    setUserId: function (id) {
      writeStorage(USER_KEY, id ? String(id) : '');
    },

    getLocationId: function () {
      return readStorage(LOCATION_KEY);
    },

    getLocationName: function () {
      return readStorage(LOCATION_NAME_KEY);
    },

    setLocation: function (id, name) {
      writeStorage(LOCATION_KEY, id != null && id !== '' ? String(id) : '');
      writeStorage(LOCATION_NAME_KEY, name ? String(name) : '');
    }
  };

  global.MBMS_CONFIG = config;
})(window);
