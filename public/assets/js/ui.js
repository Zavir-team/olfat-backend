/**
 * UI helpers: notifications, loading, empty/error states, form submit UX.
 */
(function (global) {
  'use strict';

  var utils = global.MBMS_UTILS;

  function ensureToastRoot() {
    var root = document.getElementById('mbms-toast-root');
    if (!root) {
      root = document.createElement('div');
      root.id = 'mbms-toast-root';
      root.setAttribute('aria-live', 'polite');
      document.body.appendChild(root);
    }
    return root;
  }

  function notify(options) {
    if (typeof options === 'string') {
      options = { message: options };
    }
    options = options || {};
    var type = options.type || 'info';
    var root = ensureToastRoot();
    var toast = document.createElement('div');
    toast.className = 'mbms-toast is-' + type;
    toast.setAttribute('role', 'status');
    var title = options.title ? '<strong class="d-block mb-1">' + utils.escapeHtml(options.title) + '</strong>' : '';
    toast.innerHTML = title + '<div>' + utils.escapeHtml(options.message || '') + '</div>';
    root.appendChild(toast);
    global.setTimeout(function () {
      if (toast.parentNode) {
        toast.parentNode.removeChild(toast);
      }
    }, options.duration || 3500);
    return toast;
  }

  function setButtonLoading(button, isLoading) {
    if (!button) {
      return;
    }
    if (isLoading) {
      button.dataset.originalHtml = button.innerHTML;
      button.disabled = true;
      button.setAttribute('aria-busy', 'true');
      button.innerHTML = '<span class="spinner-border spinner-border-sm" aria-hidden="true"></span><span class="ms-2">Please wait</span>';
    } else {
      button.disabled = false;
      button.removeAttribute('aria-busy');
      if (button.dataset.originalHtml) {
        button.innerHTML = button.dataset.originalHtml;
      }
    }
  }

  function showLoading(target, message) {
    var el = typeof target === 'string' ? document.querySelector(target) : target;
    if (!el) {
      return;
    }
    el.classList.add('mbms-is-loading');
    var overlay = el.querySelector(':scope > .mbms-loading-overlay');
    if (!overlay) {
      overlay = document.createElement('div');
      overlay.className = 'mbms-loading-overlay';
      overlay.innerHTML =
        '<div class="mbms-spinner" role="status" aria-label="Loading"></div>' +
        (message ? '<p class="small mb-0">' + utils.escapeHtml(message) + '</p>' : '');
      el.appendChild(overlay);
    }
    if (el._mbmsLoadingTimer) {
      global.clearTimeout(el._mbmsLoadingTimer);
    }
    el._mbmsLoadingTimer = global.setTimeout(function () {
      hideLoading(el);
    }, 8000);
  }

  function hideLoading(target) {
    var el = typeof target === 'string' ? document.querySelector(target) : target;
    if (!el) {
      return;
    }
    el.classList.remove('mbms-is-loading');
    if (el._mbmsLoadingTimer) {
      global.clearTimeout(el._mbmsLoadingTimer);
      el._mbmsLoadingTimer = null;
    }
    var overlay = el.querySelector(':scope > .mbms-loading-overlay');
    if (overlay) {
      overlay.parentNode.removeChild(overlay);
    }
  }

  function renderState(target, options) {
    var el = typeof target === 'string' ? document.querySelector(target) : target;
    if (!el) {
      return;
    }
    options = options || {};
    var action = options.actionLabel
      ? '<button type="button" class="btn btn-primary mt-3" data-state-action>' +
        utils.escapeHtml(options.actionLabel) +
        '</button>'
      : '';
    el.innerHTML =
      '<div class="mbms-state">' +
      '<h2 class="h5">' +
      utils.escapeHtml(options.title || 'Nothing to show') +
      '</h2>' +
      '<p>' +
      utils.escapeHtml(options.message || '') +
      '</p>' +
      action +
      '</div>';
    var button = el.querySelector('[data-state-action]');
    if (button && typeof options.onAction === 'function') {
      button.addEventListener('click', options.onAction);
    }
  }

  function renderEmpty(target, options) {
    options = options || {};
    options.title = options.title || 'Nothing to show yet';
    options.message = options.message || 'When records exist, they will appear here.';
    renderState(target, options);
  }

  function renderError(target, options) {
    options = options || {};
    options.title = options.title || 'Unable to load';
    options.message = options.message || 'Please try again. If this continues, check the connection to the server.';
    renderState(target, options);
  }

  function confirmAction(options) {
    options = options || {};
    return new Promise(function (resolve) {
      var modalEl = document.getElementById('mbms-confirm-modal');
      if (!modalEl) {
        modalEl = document.createElement('div');
        modalEl.id = 'mbms-confirm-modal';
        modalEl.className = 'modal fade';
        modalEl.tabIndex = -1;
        modalEl.innerHTML =
          '<div class="modal-dialog modal-dialog-centered"><div class="modal-content">' +
          '<div class="modal-header"><h2 class="modal-title h5" id="mbms-confirm-title"></h2>' +
          '<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>' +
          '<div class="modal-body" id="mbms-confirm-body"></div>' +
          '<div class="modal-footer">' +
          '<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" id="mbms-confirm-cancel">Cancel</button>' +
          '<button type="button" class="btn btn-danger" id="mbms-confirm-ok">Confirm</button>' +
          '</div></div></div>';
        document.body.appendChild(modalEl);
      }
      document.getElementById('mbms-confirm-title').textContent = options.title || 'Please confirm';
      document.getElementById('mbms-confirm-body').textContent = options.message || 'Continue with this action?';
      var ok = document.getElementById('mbms-confirm-ok');
      ok.textContent = options.confirmLabel || 'Confirm';
      ok.className = 'btn ' + (options.danger === false ? 'btn-primary' : 'btn-danger');
      var modal = global.bootstrap.Modal.getOrCreateInstance(modalEl);
      var settled = false;
      function finish(value) {
        if (settled) {
          return;
        }
        settled = true;
        modal.hide();
        resolve(value);
      }
      ok.onclick = function () {
        finish(true);
      };
      modalEl.addEventListener('hidden.bs.modal', function onHidden() {
        modalEl.removeEventListener('hidden.bs.modal', onHidden);
        finish(false);
      });
      modal.show();
    });
  }

  function isFormDirty(form) {
    return Array.prototype.some.call(form.elements, function (el) {
      if (!el.name || el.disabled || el.type === 'hidden' || el.type === 'submit' || el.type === 'button') {
        return false;
      }
      return String(el.value || '') !== '';
    });
  }

  function bindReset(form, button, afterReset) {
    if (!form || !button) {
      return;
    }
    button.addEventListener('click', function () {
      var reset = function () {
        form.reset();
        form.classList.remove('was-validated');
        if (utils.clearFormErrors) {
          utils.clearFormErrors(form);
        }
        if (typeof afterReset === 'function') {
          afterReset(form);
        }
      };
      if (!isFormDirty(form)) {
        reset();
        return;
      }
      confirmAction({
        title: 'Reset form',
        message: 'Clear the entered values?',
        confirmLabel: 'Reset',
        danger: false
      }).then(function (ok) {
        if (ok) {
          reset();
        }
      });
    });
  }

  function bindForm(form, handlers) {
    handlers = handlers || {};
    form.addEventListener('submit', function (event) {
      event.preventDefault();
      if (form.dataset.submitting === '1') {
        return;
      }
      utils.clearFormErrors(form);
      if (!form.checkValidity()) {
        form.classList.add('was-validated');
        return;
      }
      var submitter = form.querySelector('[type="submit"]');
      form.dataset.submitting = '1';
      setButtonLoading(submitter, true);
      var pending;
      try {
        pending = Promise.resolve(handlers.onSubmit(utils.formToObject(form), form));
      } catch (error) {
        pending = Promise.reject(error);
      }
      pending
        .catch(function (error) {
          var fieldErrors = utils.getValidationErrors(error);
          if (Object.keys(fieldErrors).length) {
            utils.setFormErrors(form, fieldErrors);
          }
          if (!handlers.silentErrors) {
            notify({
              type: 'danger',
              title: 'Form error',
              message: utils.getErrorMessage(error, 'Please check the form and try again.')
            });
          }
          if (typeof handlers.onError === 'function') {
            handlers.onError(error, form);
          }
        })
        .finally(function () {
          form.dataset.submitting = '';
          setButtonLoading(submitter, false);
        });
    });
  }

  global.MBMS_UI = {
    notify: notify,
    setButtonLoading: setButtonLoading,
    showLoading: showLoading,
    hideLoading: hideLoading,
    renderEmpty: renderEmpty,
    renderError: renderError,
    bindForm: bindForm,
    bindReset: bindReset,
    confirm: confirmAction
  };
})(window);
