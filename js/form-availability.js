/**
 * Rentman realtime availability check for form date fields.
 *
 * Works with Gravity Forms ('gf') and Fluent Forms ('ff'). Each rendered form
 * that should be watched is described in window.racFormConfigs (added by PHP):
 *   { platform, formId, dateField, blockUnavailable, msgPosition, msgStyle }
 */
(function ($) {
    'use strict';

    var configs = window.racFormConfigs || [];
    var data = window.racFormData || {};

    if (!configs.length || !data.ajaxUrl) {
        return;
    }

    var timers = {};
    var requests = {};
    var tokens = {};

    var PLATFORMS = {
        gf: {
            formSelector: 'form[id^="gform_"]',
            fieldWrapper: '.gfield',
            getFormId: function ($form) {
                var match = ($form.attr('id') || '').match(/^gform_(\d+)$/);
                return match ? parseInt(match[1], 10) : 0;
            },
            isDateField: function ($field, dateField) {
                if (dateField) {
                    var re = new RegExp('^input_' + dateField + '(\\.\\d+)?$');
                    var name = $field.attr('name') || '';
                    var id = ($field.attr('id') || '').replace(/^input_\d+_/, 'input_');
                    return re.test(name) || re.test(id);
                }
                if ($field.attr('type') === 'date') {
                    return true;
                }
                return $field.closest('.gfield').hasClass('gfield_date') ||
                    $field.closest('.gfield').hasClass('gfield--type-date');
            },
            dateSelector: '.gfield_date input[type="text"], .gfield--type-date input[type="text"], input[type="date"]'
        },
        ff: {
            formSelector: 'form.frm-fluent-form',
            fieldWrapper: '.ff-el-group',
            getFormId: function ($form) {
                return parseInt($form.attr('data-form_id'), 10) || 0;
            },
            isDateField: function ($field, dateField) {
                if (dateField) {
                    return ($field.attr('name') || '') === dateField;
                }
                return $field.hasClass('ff-el-datepicker') || $field.attr('type') === 'date';
            },
            dateSelector: 'input.ff-el-datepicker, input[type="date"]'
        }
    };

    /**
     * Finds the config + platform that applies to a form element, or null.
     */
    function getFormContext($form) {
        for (var key in PLATFORMS) {
            if (!PLATFORMS.hasOwnProperty(key) || !$form.is(PLATFORMS[key].formSelector)) {
                continue;
            }
            var platform = PLATFORMS[key];
            var formId = platform.getFormId($form);

            for (var i = 0; i < configs.length; i++) {
                var cfg = configs[i];
                if (cfg.platform !== key) {
                    continue;
                }
                if (!cfg.formId || parseInt(cfg.formId, 10) === formId) {
                    return { platform: platform, config: cfg, key: key + ':' + formId };
                }
            }
        }
        return null;
    }

    function getFieldContext($field) {
        var $form = $field.closest('form');
        if (!$form.length) {
            return null;
        }
        var ctx = getFormContext($form);
        if (!ctx || !ctx.platform.isDateField($field, ctx.config.dateField)) {
            return null;
        }
        ctx.$form = $form;
        ctx.fieldKey = ctx.key + ':' + ($field.attr('name') || $field.attr('id') || '');
        return ctx;
    }

    $(document).on('change input', 'form input[type="text"], form input[type="date"]', function (event) {
        var $field = $(this);

        // Text inputs fire "input" on every keystroke; only react to "change" for those.
        if (event.type === 'input' && $field.attr('type') !== 'date') {
            return;
        }

        var ctx = getFieldContext($field);
        if (!ctx) {
            return;
        }
        handleDateChange($field, $field.val(), ctx);
    });

    // Gravity Forms re-renders forms via AJAX (multi-page, validation errors).
    $(document).on('gform_post_render', function (event, formId) {
        var $form = $('#gform_' + formId);
        setTimeout(function () {
            checkPrefilled($form);
        }, 100);
    });

    // Initial pass for forms already on the page (prefilled / restored values).
    $(function () {
        setTimeout(function () {
            $(PLATFORMS.gf.formSelector + ', ' + PLATFORMS.ff.formSelector).each(function () {
                checkPrefilled($(this));
            });
        }, 300);
    });

    function checkPrefilled($form) {
        if (!$form || !$form.length) {
            return;
        }
        var ctx = getFormContext($form);
        if (!ctx) {
            return;
        }
        $form.find('.rentman-availability').remove();
        $form.find(ctx.platform.dateSelector).each(function () {
            var $field = $(this);
            var fieldCtx = getFieldContext($field);
            if (fieldCtx && $field.val()) {
                handleDateChange($field, $field.val(), fieldCtx);
            }
        });
    }

    function handleDateChange($field, dateValue, ctx) {
        var key = ctx.fieldKey;
        clearExistingIndicator($field, ctx);

        if (timers[key]) {
            clearTimeout(timers[key]);
        }
        if (requests[key]) {
            requests[key].abort();
            requests[key] = null;
        }

        if (!dateValue) {
            setFormBlocked(ctx.$form, false);
            return;
        }

        if (ctx.config.msgPosition !== 'tooltip') {
            showLoadingIndicator($field, ctx);
        }

        timers[key] = setTimeout(function () {
            tokens[key] = (tokens[key] || 0) + 1;
            var token = tokens[key];

            requests[key] = $.ajax({
                url: data.ajaxUrl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'rac_check_date_availability',
                    nonce: data.nonce,
                    date: dateValue
                },
                success: function (response) {
                    if (token !== tokens[key]) {
                        return;
                    }
                    if (response && response.success) {
                        showAvailabilityIndicator($field, response.data, ctx);
                    } else {
                        showErrorIndicator($field, response && response.data && response.data.message
                            ? response.data.message
                            : data.msgError, ctx);
                    }
                },
                error: function (xhr, status) {
                    if (status === 'abort' || token !== tokens[key]) {
                        return;
                    }
                    showErrorIndicator($field, data.msgError, ctx);
                },
                complete: function () {
                    if (token === tokens[key]) {
                        requests[key] = null;
                    }
                }
            });
        }, 400);
    }

    function getFieldWrapper($field, ctx) {
        var $wrapper = $field.closest(ctx.platform.fieldWrapper);
        return $wrapper.length ? $wrapper : $field.parent();
    }

    function clearExistingIndicator($field, ctx) {
        getFieldWrapper($field, ctx).find('.rentman-availability').remove();
        if ($field.data('racTooltip')) {
            $field.data('racTooltip').remove();
            $field.removeData('racTooltip');
        }
        $field.off('.rac-tooltip');
    }

    function buildIndicatorHtml(statusClass, message, ctx) {
        var style = ctx.config.msgStyle || 'full';
        var html = '<div class="rentman-availability ' + statusClass + ' rentman-availability--' + style +
            (ctx.config.msgPosition === 'above' ? ' rentman-availability--above' : '') +
            '" role="status" aria-live="polite">';

        if (style === 'dot' || style === 'full') {
            html += '<span class="rentman-availability__dot"></span>';
        }
        if (style === 'text' || style === 'full') {
            html += '<span class="rentman-availability__text">' + escapeHtml(message || '') + '</span>';
        }
        return html + '</div>';
    }

    function showLoadingIndicator($field, ctx) {
        var $indicator = $('<div class="rentman-availability rentman-availability--loading" role="status" aria-live="polite">' +
            '<span class="rentman-availability__spinner"></span>' +
            '<span class="rentman-availability__text">' + escapeHtml(data.msgChecking || '') + '</span>' +
            '</div>');
        insertIndicator(getFieldWrapper($field, ctx), $indicator, ctx);
    }

    function showAvailabilityIndicator($field, result, ctx) {
        clearExistingIndicator($field, ctx);

        var $indicator = $(buildIndicatorHtml('rentman-availability--' + result.status, result.message, ctx));

        if (ctx.config.msgPosition === 'tooltip') {
            setupTooltip($field, $indicator);
        } else {
            insertIndicator(getFieldWrapper($field, ctx), $indicator, ctx);
        }

        setFormBlocked(ctx.$form, result.status === 'unavailable' && !!ctx.config.blockUnavailable);
    }

    function showErrorIndicator($field, message, ctx) {
        clearExistingIndicator($field, ctx);

        var $indicator = $(buildIndicatorHtml('rentman-availability--error', message, ctx));

        if (ctx.config.msgPosition === 'tooltip') {
            setupTooltip($field, $indicator);
        } else {
            insertIndicator(getFieldWrapper($field, ctx), $indicator, ctx);
        }
        setFormBlocked(ctx.$form, false);
    }

    function insertIndicator($wrapper, $indicator, ctx) {
        if (ctx.config.msgPosition === 'above') {
            $wrapper.prepend($indicator);
        } else {
            $wrapper.append($indicator);
        }
    }

    function setupTooltip($field, $indicator) {
        $indicator.addClass('rentman-availability--tooltip');
        $('body').append($indicator);
        $field.data('racTooltip', $indicator);
        positionTooltip($field, $indicator);

        $field.on('mouseenter.rac-tooltip focus.rac-tooltip', function () {
            $indicator.stop(true, true).fadeIn(150);
            positionTooltip($field, $indicator);
        });
        $field.on('mouseleave.rac-tooltip focusout.rac-tooltip', function () {
            $indicator.stop(true, true).delay(200).fadeOut(150);
        });
    }

    function positionTooltip($field, $indicator) {
        var offset = $field.offset();
        var left = offset.left + ($field.outerWidth() / 2) - ($indicator.outerWidth() / 2);

        $indicator.css({
            position: 'absolute',
            top: offset.top + $field.outerHeight() + 6,
            left: Math.max(8, left),
            zIndex: 9999,
            display: 'none'
        });
    }

    function setFormBlocked($form, blocked) {
        var $buttons = $form.find('input[type="submit"], button[type="submit"]');
        if (blocked) {
            $form.addClass('rentman-gf-blocked rentman-form-blocked');
            $buttons.attr('data-rac-blocked', '1');
        } else {
            $form.removeClass('rentman-gf-blocked rentman-form-blocked');
            $buttons.removeAttr('data-rac-blocked');
        }
    }

    // Keyboard "Enter" can still submit a blocked form; stop it here.
    // The server-side check is the real guard.
    $(document).on('submit', 'form.rentman-form-blocked', function (event) {
        event.preventDefault();
        event.stopImmediatePropagation();
        return false;
    });

    function escapeHtml(str) {
        return String(str || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }
})(jQuery);
