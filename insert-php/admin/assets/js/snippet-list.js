jQuery(document).ready(function ($) {
    $("a#winp-snippet-status-switch").on('click', function (e) {
        e.preventDefault();
        var href = $(this);
        href.addClass('winp-snippet-switch-loader');
        jQuery.post(ajaxurl, {
            action: 'change_snippet_status',
            snippet_id: href.data('snippet-id'),
            _ajax_nonce: winp_ajax.nonce,
        }).done(function (result) {
            href.removeClass('winp-snippet-switch-loader');
            if (result.error_message) {
                if (result.alert) {
                    alert(result.error_message);
                }
                console.error(result.error_message);

                href.removeClass('winp-snippet-switch-loader');
            } else {
                console.log(result.message);
                href.toggleClass('winp-inactive');
            }
        });
    });

    $("input.wbcr_inp_input_priority").on('change', function (e) {
        var previous = e.currentTarget.defaultValue;
        var input = $(this);
        input.attr('disabled', true);
        input.addClass('winp-loader');
        jQuery.post(ajaxurl, {
            action: 'change_priority',
            snippet_id: input.data('snippet-id'),
            priority: input.val(),
            _ajax_nonce: winp_ajax.nonce,
        }).done(function (result) {
            //console.log(result);
            if (result.error_message) {
                console.error(result.error_message);
                input.val(previous);
            } else {
                console.log(result.message);
            }
            input.removeAttr('disabled');
            input.removeClass('winp-loader');
        });
    });

    $("input.wbcr_inp_input_priority").on('keydown', stop_enter);
    $("input.wbcr_inp_input_priority").on('keyup', stop_enter);
    $("input.wbcr_inp_input_priority").on('keypress', stop_enter);

    // Handle click on shortcode input to copy to clipboard
    $("input.wbcr_inp_shortcode_input").on('click', function (e) {
        var input = $(this);
        var value = input.val();
        
        // Select the text
        this.setSelectionRange(0, this.value.length);
        
        // Copy to clipboard
        if (navigator.clipboard && navigator.clipboard.writeText) {
            // Modern clipboard API
            navigator.clipboard.writeText(value).then(function() {
                showCopyNotice(input);
            }).catch(function(err) {
                console.error('Failed to copy:', err);
                fallbackCopy(input);
            });
        } else {
            // Fallback for older browsers
            fallbackCopy(input);
        }
    });

    function fallbackCopy(input) {
        try {
            input[0].select();
            var successful = document.execCommand('copy');
            if (successful) {
                showCopyNotice(input);
            }
        } catch (err) {
            console.error('Failed to copy:', err);
        }
    }

    function showCopyNotice(input) {
        // Remove any existing notices
        $('.winp-copy-notice').remove();
        
        // Create and show notice
        var notice = $('<div class="winp-copy-notice"><span>Copied to clipboard!</span></div>');
        input.after(notice);
        
        // Position the notice
        notice.css({
            position: 'absolute',
            background: '#6366f1',
            color: '#fff',
            borderRadius: '3px',
            fontSize: '12px',
            zIndex: 1000,
            whiteSpace: 'nowrap',
            boxShadow: '0 2px 5px rgba(0,0,0,0.2)',
            height: '100%',
            width: '100%'
        });
        
        // Style the span
        notice.find('span').css({
            display: 'flex',
            justifyContent: 'center',
            alignItems: 'center',
            height: '100%'
        });
        
        // Fade out and remove after 2 seconds
        setTimeout(function() {
            notice.fadeOut(300, function() {
                notice.remove();
            });
        }, 2000);
    }
});

function stop_enter(e) {
    if (e.keyCode === 13) {
        e.preventDefault();
        jQuery(this).blur();
    }
}