var MOJ = MOJ || {}

jQuery(function ($) {
    MOJ.ES = {
        // we can use this global object in future for OOP
        submit: {
            button: {
                show: function () {
                    $('.moj-es .submit .button').show()
                },
                hide: function () {
                    $('.moj-es .submit .button').hide()
                },
                maybeShow: function (tab) {
                    if (tab !== 'moj-es-home') {
                        MOJ.ES.submit.button.show()
                    } else {
                        MOJ.ES.submit.button.hide()
                    }
                }
            }
        }
    }

    /**
     * This function is a setter and getter. On set it updates the browser history and returns the pathname + search
     * part of the URL. If no key is provided the function returns false. If a key with no value has been given, and
     * the query string parameter exists, the value is returned.
     *
     * @param key
     * @param value
     * @returns string|boolean false|pathname + query string
     */
    function mojQString (key, value) {
        var params = new URLSearchParams(window.location.search)

        if (!value && params.has(key)) {
            return params.get(key)
        }

        if (!key) {
            return false
        }

        params.set(key, value)
        if (!window.history) {
            /* shhh */
        } else {
            window.history.replaceState({}, '', `${location.pathname}?${params}`)
        }

        return (window.location.pathname + window.location.search)
    }

    function setTab (tab) {
        var tabId, refererPath

        if (!tab) {
            tab = $('.nav-tab-wrapper a').eq(0)
        } else {
            tab = $('.nav-tab-wrapper a[href=\'' + tab + '\']')
        }

        if (!tab.attr('href')) {
            tab = $('.nav-tab-wrapper a').eq(0)
        }

        tabId = tab.attr('href').split('#')[1]

        tab.parent().find('a').removeClass('nav-tab-active')
        tab.addClass('nav-tab-active')

        $('.moj-es-settings-group').hide()
        $('div#' + tabId).fadeIn()

        // add to query string and update _wp_http_referer
        refererPath = mojQString('tab', tabId)
        $('input[name="_wp_http_referer"]').val(refererPath)

        return false
    }

    // only run JS on our settings page
    if ($('.moj-es').length > 0) {
        $('.nav-tab-wrapper').on('click', 'a', function (e) {
            e.preventDefault()
            var tab = $(this).attr('href').substring(1)
            MOJ.ES.submit.button.maybeShow(tab)

            setTab('#' + tab)
            return false
        })

        // set the tab
        var mojTabSelected = mojQString('tab')
        MOJ.ES.submit.button.maybeShow(mojTabSelected)

        if (mojTabSelected) {
            setTab('#' + mojTabSelected)
        } else {
            setTab()
        }

        function startBulkIndex () {
            $('.moj-es a.thickbox').hide()
            $('.moj-es button.index_button').show().attr('disabled', null).click()
        }

        function killBulkIndex () {
            $('.moj-es a.thickbox.kill_index_button').hide()
            $('.moj-es button.kill_index_button').show().attr('disabled', null).click()
        }

        // listen for click of index_pre_link
        $('.moj-es a.index_pre_link').on('click', startBulkIndex)
        $('a.kill_index_pre_link').on('click', killBulkIndex)

        var polling_num = 0
        var statInterval = null
        var subStatInterval = null;
        // self-executing function; get latest stats
        (function get_stats () {
            if (!statInterval) {
                statInterval = setInterval(get_stats, mojESPollingTime * 1000)
            }

            $.post(ajaxurl, { 'action': 'stats_load' }, function (response) {
                var json = $.parseJSON(response)
                if (json && !json.stats) {
                    $('#moj-es-indexing-stats').html(json)
                }
                clearInterval(subStatInterval)
                subStatInterval = setInterval(function () {
                    moj_increment_seconds('#index-time')
                }, 1000)

                polling_num++
                if (polling_num > 15) {
                    clearInterval(statInterval)
                    statInterval = null
                    polling_num = 1
                    setTimeout(get_stats, 8000)
                }
            })
        })()

        $('#wpbody-content > div[id^="setting-error-"]').remove()

        $('input[name*="storage_is_db"]').on('click', function () {
            $('#storage_indicator').text(($(this).is(':checked') ? 'Yes, store in DB.' : 'No, write to disc.'))
        })

        $('input[name*="force_clean_up"]').on('click', function () {
            $('#force_clean_up_indicator').text(($(this).is(':checked') ? 'Yes, clean up.' : 'No.'))
        })

        $('input[name*="force_wp_query"]').on('click', function () {
            $('#force_wp_query_indicator').text(($(this).is(':checked') ? 'Yes, force WP Query while indexing.' : 'No.'))
        })
    }

    function moj_increment_seconds(element) {
        var time = $(element).text(),
            timeParts, hours, minutes, seconds

        if (time) {
            timeParts = time.split(' ')
            hours = Number(timeParts[0].slice(0, -1))
            minutes = Number(timeParts[1].slice(0, -1))
            seconds = Number(timeParts[2].slice(0, -1))

            // not past 59
            seconds = String(seconds === 59 ? '00' : (seconds + 1));
            minutes = seconds === '00' ? (minutes + 1) : minutes;
            minutes = String(minutes === 59 ? '00' : minutes);
            if (minutes === '00') {
                hours = String((hours + 1));
            }

            seconds = (seconds.length === 1 ? '0' + seconds : seconds);
            minutes = (minutes.length === 1 ? '0' + minutes : minutes);

            $(element).text(hours + 'h ' + minutes + 'm ' + seconds + 's');
        }
    }
})

/**
 * Generates a unique string in the form of a UUID
 * @returns string UUID
 */
function uuidv4 () {
    return ([1e7] + -1e3 + -4e3 + -8e3 + -1e11).replace(
        /[018]/g,
        c => (c ^ crypto.getRandomValues(new Uint8Array(1))[0] & 15 >> c / 4).toString(16)
    )
}
