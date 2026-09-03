(function ($) {
    'use strict';

    $(document).ready(function () {
        $('.rac-calendar-wrap').each(function () {
            initCalendar($(this));
        });
    });

    function initCalendar($wrap) {
        var $label = $wrap.find('.rac-month-label');
        var $tableWrap = $wrap.find('.rac-calendar-table-wrap');
        var $loading = $wrap.find('.rac-calendar-loading');
        var $error = $wrap.find('.rac-calendar-error');
        var $errorP = $error.find('p');
        var $prevBtn = $wrap.find('.rac-prev-month');
        var $nextBtn = $wrap.find('.rac-next-month');
        var $initialData = $wrap.find('#rac-initial-data');

        var initialData = {};
        try {
            initialData = JSON.parse($initialData.text());
        } catch (e) {
            initialData = { error: true };
        }

        if (!initialData.error) {
            renderGrid($tableWrap, initialData);
        } else {
            showError('Failed to load initial data.');
        }

        $prevBtn.on('click', function () {
            var year = parseInt($label.data('year'), 10);
            var month = parseInt($label.data('month'), 10);
            month--;
            if (month < 1) {
                month = 12;
                year--;
            }
            fetchMonth(year, month);
        });

        $nextBtn.on('click', function () {
            var year = parseInt($label.data('year'), 10);
            var month = parseInt($label.data('month'), 10);
            month++;
            if (month > 12) {
                month = 1;
                year++;
            }
            fetchMonth(year, month);
        });

        function fetchMonth(year, month) {
            $loading.show();
            $error.hide();
            $tableWrap.hide();

            $.ajax({
                url: racData.ajaxUrl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'rac_get_month_data',
                    nonce: racData.nonce,
                    year: year,
                    month: month
                },
                success: function (response) {
                    $loading.hide();
                    if (response.success) {
                        var data = response.data;
                        $label.data('year', data.year).data('month', data.month);
                        $label.text(data.monthLabel);
                        renderGrid($tableWrap, data);
                        $tableWrap.show();
                    } else {
                        showError(response.data && response.data.message
                            ? response.data.message
                            : 'An error occurred.');
                    }
                },
                error: function () {
                    $loading.hide();
                    showError('Connection failed. Please try again.');
                }
            });
        }

        function showError(msg) {
            $errorP.text(msg);
            $error.show();
            $tableWrap.hide();
            $loading.hide();
        }

        var tooltip = null;

        function getTooltip() {
            if (!tooltip) {
                tooltip = $('<div class="rac-tooltip"></div>').appendTo('body');
            }
            return tooltip;
        }

        $(document).on('mouseenter', '.rac-calendar-table td.rac-day', function () {
            var $cell = $(this);
            var count = parseInt($cell.data('count'), 10) || 0;
            var day = parseInt($cell.data('day'), 10);

            var dayData = findDayData(day);
            if (!dayData) return;

            var $tip = getTooltip();
            var html = '<div class="rac-tooltip-title">' + dayData.count +
                (dayData.count === 1 ? ' appointment' : ' appointments') + '</div>';

            if (dayData.details && dayData.details.length > 0) {
                dayData.details.forEach(function (d) {
                    var time = formatTime(d.start, d.end);
                    html += '<div class="rac-tooltip-item">';
                    html += '<strong>' + escapeHtml(d.name || 'Untitled') + '</strong>';
                    if (time) html += '<br>' + escapeHtml(time);
                    if (d.location) html += '<br>' + escapeHtml(d.location);
                    html += '</div>';
                });
            } else {
                html += '<div class="rac-tooltip-item">No appointments scheduled</div>';
            }

            $tip.html(html).addClass('visible');
            positionTooltip($tip, $cell);
        });

        $(document).on('mouseleave', '.rac-calendar-table td.rac-day', function () {
            if (tooltip) {
                tooltip.removeClass('visible');
            }
        });

        var currentData = initialData;

        function findDayData(day) {
            if (!currentData || !currentData.days) return null;
            for (var i = 0; i < currentData.days.length; i++) {
                if (currentData.days[i].day === day) {
                    return currentData.days[i];
                }
            }
            return null;
        }

        function updateCurrentData(data) {
            currentData = data;
        }

        function renderGrid($container, data) {
            updateCurrentData(data);

            if (!data || !data.days) {
                $container.html('<p class="rac-error">No data available.</p>');
                return;
            }

            var weekdays = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
            var year = data.year;
            var month = data.month;
            var daysInMonth = data.days.length;

            var firstWeekday = new Date(Date.UTC(year, month - 1, 1)).getUTCDay();

            var html = '<table class="rac-calendar-table"><thead><tr>';
            weekdays.forEach(function (wd) {
                html += '<th>' + wd + '</th>';
            });
            html += '</tr></thead><tbody><tr>';

            for (var i = 0; i < firstWeekday; i++) {
                html += '<td class="rac-empty"></td>';
            }

            var today = new Date();
            var todayY = today.getUTCFullYear();
            var todayM = today.getUTCMonth() + 1;
            var todayD = today.getUTCDate();

            for (var d = 1; d <= daysInMonth; d++) {
                var dayData = data.days[d - 1] || { count: 0, level: 'green' };
                var level = dayData.level || 'green';
                var count = dayData.count || 0;
                var isToday = (todayY === year && todayM === month && todayD === d);

                html += '<td class="rac-day rac-' + level + (isToday ? ' rac-today' : '') +
                    '" data-day="' + d + '" data-count="' + count + '">' +
                    '<span class="rac-day-number">' + d + '</span>' +
                    '<span class="rac-day-count">' + count + '</span>' +
                    '</td>';

                var weekday = new Date(Date.UTC(year, month - 1, d)).getUTCDay();
                if (weekday === 6 && d < daysInMonth) {
                    html += '</tr><tr>';
                }
            }

            var lastWeekday = new Date(Date.UTC(year, month - 1, daysInMonth)).getUTCDay();
            for (var j = lastWeekday + 1; j <= 6; j++) {
                html += '<td class="rac-empty"></td>';
            }

            html += '</tr></tbody></table>';
            $container.html(html);
        }

        function positionTooltip($tip, $cell) {
            var cellOffset = $cell.offset();
            var cellWidth = $cell.outerWidth();
            var tipWidth = $tip.outerWidth();
            var tipHeight = $tip.outerHeight();
            var scrollTop = $(window).scrollTop();

            var left = cellOffset.left + (cellWidth / 2) - (tipWidth / 2);
            var top = cellOffset.top - tipHeight - 8;

            if (top < scrollTop) {
                top = cellOffset.top + $cell.outerHeight() + 8;
            }

            if (left < 5) left = 5;
            if (left + tipWidth > $(window).width() - 5) {
                left = $(window).width() - tipWidth - 5;
            }

            $tip.css({ top: top, left: left });
        }

        function formatTime(start, end) {
            var s = start ? start.replace(/:\d{2}$/, '') : '';
            var e = end ? end.replace(/:\d{2}$/, '') : '';
            if (s && e) return s + ' - ' + e;
            if (s) return s;
            return '';
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
    }
})(jQuery);
