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

        showLoadingIndicator($field);

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
    }

    function showLoadingIndicator($field) {
        var $wrapper = getFieldWrapper($field);
        var $indicator = $('<div class="rentman-availability rentman-availability--loading">' +
            '<span class="rentman-availability__spinner"></span>' +
            '<span class="rentman-availability__text">Beschikbaarheid controleren...</span>' +
            '</div>');
        $wrapper.append($indicator);
    }

    function showAvailabilityIndicator($field, data) {
        var $wrapper = getFieldWrapper($field);
        $wrapper.find('.rentman-availability').remove();

        var statusClass = 'rentman-availability--' + data.status;
        var $indicator = $('<div class="rentman-availability ' + statusClass + '">' +
            '<span class="rentman-availability__dot"></span>' +
            '<span class="rentman-availability__text">' + escapeHtml(data.message || '') + '</span>' +
            '</div>');

        $wrapper.append($indicator);

        if (data.status === 'unavailable' && config.blockUnavailable) {
            setFormBlocked($field, true);
        } else {
            setFormBlocked($field, false);
        }
    }

    function showErrorIndicator($field, message) {
        var $wrapper = getFieldWrapper($field);
        $wrapper.find('.rentman-availability').remove();

        var $indicator = $('<div class="rentman-availability rentman-availability--error">' +
            '<span class="rentman-availability__dot"></span>' +
            '<span class="rentman-availability__text">' + escapeHtml(message) + '</span>' +
            '</div>');

        $wrapper.append($indicator);
        setFormBlocked($field, false);
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
