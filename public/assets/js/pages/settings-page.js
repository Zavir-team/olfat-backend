/**
 * Settings: profile, account, local appearance, and API base URL.
 * Does not invent administrator roles or permissions.
 */
(function (global) {
  'use strict';

  var DENSITY_KEY = 'MBMS_UI_DENSITY';

  function mockProfile() {
    return (global.MBMS_MOCK && global.MBMS_MOCK.profile) || { name: 'Signed-in user', email: '', location: '' };
  }

  function applyDensity(value) {
    document.body.classList.toggle('mbms-compact', value === 'compact');
    try {
      global.localStorage.setItem(DENSITY_KEY, value);
    } catch (err) {
      /* ignore */
    }
  }

  function currentDensity() {
    try {
      return global.localStorage.getItem(DENSITY_KEY) || 'comfortable';
    } catch (err) {
      return 'comfortable';
    }
  }

  function loadProfile() {
    return global.MBMS_RECORDS.fetchOrMock('settings/profile', mockProfile)
      .then(function (result) {
        var profile = result.source === 'api' ? (global.MBMS_UTILS.unwrap(result.data) || result.data || mockProfile()) : mockProfile();
        var locationId = profile.location_id || profile.location;
        var match = (global.MBMS_RECORDS.locations() || []).filter(function (row) {
          return row.id === locationId || row.name === locationId;
        })[0];
        global.MBMS_UTILS.fillForm(document.getElementById('profile-form'), Object.assign({}, profile, {
          location: match ? match.id : locationId
        }));
        global.MBMS_RECORDS.showBanner(
          document.getElementById('source-banner'),
          result.source,
          'Showing a sample profile. Official user details come from Laravel.'
        );
      })
      .catch(function (error) {
        global.MBMS_UI.notify({
          type: 'danger',
          title: 'Unable to load profile',
          message: global.MBMS_UTILS.getErrorMessage(error)
        });
      });
  }

  global.MBMS_PAGE = {
    active: 'settings',
    title: 'Settings',
    onReady: function () {
      applyDensity(currentDensity());
      document.querySelectorAll('[name="density"]').forEach(function (input) {
        input.checked = input.value === currentDensity();
        input.addEventListener('change', function () {
          applyDensity(input.value);
          global.MBMS_UI.notify({ type: 'success', message: 'Display density saved on this device only.' });
        });
      });

      document.querySelectorAll('[name="language"]').forEach(function (input) {
        input.checked = input.value === (global.MBMS_I18N ? global.MBMS_I18N.lang() : 'en');
        input.addEventListener('change', function () {
          if (global.MBMS_I18N) {
            global.MBMS_I18N.set(input.value);
          }
        });
      });

      document.getElementById('api-base').value = global.MBMS_CONFIG.apiBaseUrl;
      global.MBMS_RECORDS.fillSelect(
        document.getElementById('profile-location'),
        global.MBMS_RECORDS.locations(),
        function (row) { return row.id; },
        function (row) { return row.name; }
      );

      global.MBMS_UI.bindForm(document.getElementById('profile-form'), {
        onSubmit: function (values) {
          var body = {
            name: values.name,
            email: values.email
          };
          return global.MBMS_RECORDS.submitOrMock('put', 'settings/profile', body, function () {
            return body;
          }).then(function (result) {
            global.MBMS_UI.notify({
              type: result.source === 'api' ? 'success' : 'warning',
              message: result.source === 'api' ? 'Profile saved.' : 'API not connected. Profile was not stored on the server.'
            });
            return loadProfile();
          });
        }
      });

      global.MBMS_UI.bindForm(document.getElementById('password-form'), {
        onSubmit: function (values) {
          if (values.password !== values.password_confirmation) {
            throw { message: 'The new password and confirmation do not match.', errors: { password_confirmation: ['Must match'] } };
          }
          return global.MBMS_RECORDS.submitOrMock('put', 'settings/password', values, function () {
            throw { message: 'Password changes require the Laravel API. Nothing was saved locally.', isNetworkError: false };
          }).then(function (result) {
            global.MBMS_UI.notify({
              type: result.source === 'api' ? 'success' : 'warning',
              message: result.source === 'api' ? 'Password updated.' : 'API not connected. Password was not changed.'
            });
            document.getElementById('password-form').reset();
          });
        }
      });

      global.MBMS_UI.bindForm(document.getElementById('app-form'), {
        onSubmit: function (values) {
          global.MBMS_CONFIG.setApiBaseUrl(values.api_base_url);
          global.MBMS_UI.notify({
            type: 'success',
            message: 'API base URL saved on this device. No production URL is hard-coded in page scripts.'
          });
        }
      });

      document.getElementById('reset-api').addEventListener('click', function () {
        global.MBMS_CONFIG.resetApiBaseUrl();
        document.getElementById('api-base').value = global.MBMS_CONFIG.apiBaseUrl;
        global.MBMS_UI.notify({ type: 'success', message: 'API base URL reset to the local default.' });
      });

      loadProfile();
    }
  };
})(window);
