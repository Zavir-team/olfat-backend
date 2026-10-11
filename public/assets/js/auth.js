/**
 * Auth helpers for the future Laravel login API.
 * This does not create a local/fake login session.
 */
(function (global) {
  'use strict';

  var LOGIN_PATH = 'auth/login';
  var LOGIN_OPTIONS_PATH = 'auth/login-options';
  var LOGOUT_PATH = 'auth/logout';

  function asset(path) {
    return (global.MBMS_NAV && global.MBMS_NAV.asset)
      ? global.MBMS_NAV.asset(path)
      : path;
  }

  function extractToken(payload) {
    var data = global.MBMS_UTILS ? global.MBMS_UTILS.unwrap(payload) : payload;
    if (!data || typeof data !== 'object') {
      return '';
    }
    return data.token || data.access_token || data.plainTextToken || '';
  }

  function loginOptions() {
    return global.MBMS_API.get(LOGIN_OPTIONS_PATH).then(function (response) {
      var payload = response && response.data;
      if (global.MBMS_UTILS && typeof global.MBMS_UTILS.unwrap === 'function') {
        return global.MBMS_UTILS.unwrap(payload) || payload || {};
      }
      return (payload && payload.data) || payload || {};
    });
  }

  function login(credentials) {
    return global.MBMS_API.post(LOGIN_PATH, {
      email: credentials.email,
      password: credentials.password,
      location_id: credentials.location_id
    }).then(function (response) {
      var payload = response.data;
      var token = extractToken(payload);
      var user = payload && payload.user;
      if (!user && payload && payload.data && payload.data.user) {
        user = payload.data.user;
      }
      if (token && global.MBMS_CONFIG) {
        global.MBMS_CONFIG.setAuthToken(token);
        if (user && user.id && global.MBMS_CONFIG.setUserId) {
          global.MBMS_CONFIG.setUserId(user.id);
        }
        if (user && global.MBMS_CONFIG.setLocation) {
          var locationId = user.location_id != null
            ? user.location_id
            : (user.location && user.location.id);
          var locationName = (user.location && user.location.name) || '';
          global.MBMS_CONFIG.setLocation(locationId, locationName);
        }
        try {
          // Login already established identity — skip immediate auth/me on Dashboard.
          sessionStorage.setItem('MBMS_AUTH_ME_OK', '1');
        } catch (err) {}
      }
      return {
        response: response,
        token: token,
        payload: payload,
        user: user || null
      };
    });
  }

  function logout() {
    var finish = function () {
      if (global.MBMS_API && typeof global.MBMS_API.clearSessionCache === 'function') {
        global.MBMS_API.clearSessionCache();
      }
      if (global.MBMS_CONFIG) {
        global.MBMS_CONFIG.clearAuthToken();
      }
      global.location.href = asset('pages/login.html');
    };

    return global.MBMS_API.post(LOGOUT_PATH).catch(function () {
      return null;
    }).then(finish);
  }

  function isSignedIn() {
    return !!(global.MBMS_CONFIG && global.MBMS_CONFIG.getAuthToken());
  }

  global.MBMS_AUTH = {
    loginPath: LOGIN_PATH,
    loginOptionsPath: LOGIN_OPTIONS_PATH,
    logoutPath: LOGOUT_PATH,
    loginOptions: loginOptions,
    login: login,
    logout: logout,
    isSignedIn: isSignedIn,
    extractToken: extractToken
  };
})(window);
