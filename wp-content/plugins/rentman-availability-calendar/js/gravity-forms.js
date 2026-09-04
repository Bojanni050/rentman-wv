(function ($) {
    'use strict';

    if (typeof window.racGfConfig === 'undefined') {
        return;
    }

    var config = window.racGfConfig;
    var debounceTimer = null;
    var currentRequest = null;
    var currentRequestToken = 0;

    $(document).on('change', 'input[type="text"], input[type="date"]', function () {
        var $field = $(this);

        if (!isTargetDateField($field)) {
            return;
        }

        var dateValue = $field.val();
        handleDateChange($field, dateValue);
    });

    $(document).on('input', 'input[type="date"]', function () {
        var $field = $(this);

        if (!isTargetDateField($field)) {
            return;
        }

        var dateValue = $field.val();
        handleDateChange($field, dateValue);
    });

    $(document).on('gform_post_render', function (event, formId, currentPage) {
        if (config.formId && parseInt(formId, 10) !== config.formId) {
            return;
        }

        $('.rentman-availability').remove();

        setTimeout(function () {
            findDateFields().each(function () {
                var $field = $(this);
                var dateValue = $field.val();
                if (dateValue) {
                    handleDateChange($field, dateValue);
                }
            });
        }, 100);
    });

    function isTargetDateField($field) {
        if (config.dateFieldId) {
            var name = $field.attr('name');
            if (name && name.indexOf('input_' + config.dateFieldId) !== -1) {
                return true;
            }
            var id = $field.attr('id');
            if (id && id.indexOf('input_' + config.dateFieldId) !== -1) {
                return true;
            }
            return false;
        }

        var type = $field.attr('type');
        if (type === 'date') {
            return true;
        }

        var name = $field.attr('name') || '';
        if (name.indexOf('input_') === 0 && name.match(/input_\d+(\.\d+)?$/)) {
            return $field.closest('.gfield').hasClass('gfield_date');
        }

        return false;
    }

    function findDateFields() {
        if (config.dateFieldId) {
            return $('input[name*="input_' + config.dateFieldId + '"]');
        }

        return $('.gfield_date input[type="text"], .gfield_date input[type="date"], input[type="date"]');
    }

    function handleDateChange($field, dateValue) {
        clearExistingIndicator($field);

        if (!dateValue || dateValue === '') {
            return;
        }

        if (config.msgPosition !== 'tooltip') {
            showLoadingIndicator($field);
        }

        if (debounceTimer) {
            clearTimeout(debounceTimer);
        }

        if (currentRequest) {
            currentRequest.abort();
            currentRequest = null;
        }

        debounceTimer = setTimeout(function () {
            currentRequestToken++;
            var token = currentRequestToken;

            currentRequest = $.ajax({
                url: racGfData.ajaxUrl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'rac_check_date_availability',
                    nonce: racGfData.nonce,
                    date: dateValue
                },
                success: function (response) {
                    if (token !== currentRequestToken) {
                        return;
                    }

                    if (response.success) {
                        showAvailabilityIndicator($field, response.data);
                    } else {
                        showErrorIndicator($field, response.data && response.data.message
                            ? response.data.message
                            : 'Er is een fout opgetreden.');
                    }
                },
                error: function (xhr, status) {
                    if (status === 'abort') {
                        return;
                    }
                    if (token !== currentRequestToken) {
                        return;
                    }
                    showErrorIndicator($field, 'Er is een fout opgetreden.');
                },
                complete: function () {
                    if (token === currentRequestToken) {
                        currentRequest = null;
                    }
                }
            });
        }, 400);
    }

    function clearExistingIndicator($field) {
        var $wrapper = getFieldWrapper($field);
        $wrapper.find('.rentman-availability').remove();
        $field.off('.rac-tooltip');
    }

    function buildIndicatorHtml(statusClass, message) {
        var styleClass = 'rentman-availability--' + config.msgStyle;
        var html = '<div class="rentman-availability ' + statusClass + ' ' + styleClass + '">';

        if (config.msgStyle === 'dot' || config.msgStyle === 'full') {
            html += '<span class="rentman-availability__dot"></span>';
        }

        if (config.msgStyle === 'text' || config.msgStyle === 'full') {
            html += '<span class="rentman-availability__text">' + escapeHtml(message || '') + '</span>';
        }

        html += '</div>';
        return html;
    }

    function showLoadingIndicator($field) {
        var $wrapper = getFieldWrapper($field);
        var $indicator = $('<div class="rentman-availability rentman-availability--loading">' +
            '<span class="rentman-availability__spinner"></span>' +
            '<span class="rentman-availability__text">Beschikbaarheid controleren...</span>' +
            '</div>');
        insertIndicator($wrapper, $indicator);
    }

    function showAvailabilityIndicator($field, data) {
        var $wrapper = getFieldWrapper($field);
        $wrapper.find('.rentman-availability').remove();

        var statusClass = 'rentman-availability--' + data.status;
        var $indicator = $(buildIndicatorHtml(statusClass, data.message));

        if (config.msgPosition === 'tooltip') {
            setupTooltip($field, $indicator);
        } else {
            insertIndicator($wrapper, $indicator);
        }

        if (data.status === 'unavailable' && config.blockUnavailable) {
            setFormBlocked($field, true);
        } else {
            setFormBlocked($field, false);
        }
    }

    function showErrorIndicator($field, message) {
        var $wrapper = getFieldWrapper($field);
        $wrapper.find('.rentman-availability').remove();

        var $indicator = $(buildIndicatorHtml('rentman-availability--error', message));

        if (config.msgPosition === 'tooltip') {
            setupTooltip($field, $indicator);
        } else {
            insertIndicator($wrapper, $indicator);
        }
        setFormBlocked($field, false);
    }

    function insertIndicator($wrapper, $indicator) {
        if (config.msgPosition === 'above') {
            $wrapper.prepend($indicator);
        } else {
            $wrapper.append($indicator);
        }
    }

    function setupTooltip($field, $indicator) {
        $indicator.addClass('rentman-availability--tooltip');
        $('body').append($indicator);
        positionTooltip($field, $indicator);

        $field.on('mouseenter.rac-tooltip', function () {
            $indicator.stop(true, true).fadeIn(150);
            positionTooltip($field, $indicator);
        });

        $field.on('mouseleave.rac-tooltip focusout.rac-tooltip', function () {
            $indicator.stop(true, true).delay(200).fadeOut(150);
        });

        $field.on('focus.rac-tooltip', function () {
            $indicator.stop(true, true).fadeIn(150);
            positionTooltip($field, $indicator);
        });
    }

    function positionTooltip($field, $indicator) {
        var offset = $field.offset();
        var fieldHeight = $field.outerHeight();
        var indicatorWidth = $indicator.outerWidth();
        var left = offset.left + ($field.outerWidth() / 2) - (indicatorWidth / 2);

        if (left < 8) {
            left = 8;
        }

        $indicator.css({
            position: 'absolute',
            top: offset.top + fieldHeight + 6,
            left: left,
            zIndex: 9999,
            display: 'none'
        });
    }

    function setFormBlocked($field, blocked) {
        var $form = $field.closest('form');

        if (blocked) {
            $form.addClass('rentman-gf-blocked');
            $form.find('input[type="submit"], button[type="submit"]').attr('data-rac-blocked', '1');
        } else {
            $form.removeClass('rentman-gf-blocked');
            $form.find('input[type="submit"], button[type="submit"]').removeAttr('data-rac-blocked');
        }
    }

    function getFieldWrapper($field) {
        var $gfield = $field.closest('.gfield');
        if ($gfield.length) {
            return $gfield;
        }
        return $field.parent();
    }

    function escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }
})(jQuery);
