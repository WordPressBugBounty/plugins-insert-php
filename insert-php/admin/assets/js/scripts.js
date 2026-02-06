(function($){
    $(document).on( 'click', '.winp-enable-php-btn', function(e) {
        e.preventDefault();
        var icon  = $(this).children('i'),
            label = $(this).children('.winp-btn-title'),
            input = $(this).children('.winp-with-php');

        $(this).toggleClass('winp-active');

        if( $(this).hasClass('winp-active') ) {
            icon.attr( 'class', 'dashicons dashicons-edit' );
            label.text( $(this).data( 'disable-text' ) );
            input.val('enabled');
            $('body').addClass( 'winp-snippet-enabled' );
        } else {
            icon.attr( 'class', 'dashicons dashicons-editor-code' );
            label.text( $(this).data( 'enable-text' ) );
            input.val('');
            $('body').removeClass( 'winp-snippet-enabled' );
        }
    });

})(jQuery);

jQuery(document).ready( function($) {
    // Permalink slug
    $( '#titlediv' ).on( 'click', '.winp-edit-slug', function() {
        var i,
            $el, revert_e,
            c = 0,
            slug_value = $('#editable-post-name').html(),
            real_slug = $('#post_name'),
            revert_slug = real_slug.val(),
            permalink = $( '#sample-permalink' ),
            permalinkOrig = permalink.html(),
            permalinkInner = $( '#sample-permalink a' ).html(),
            permalinkHref = $('#sample-permalink a').attr('href'),
            buttons = $('#winp-edit-slug-buttons'),
            buttonsOrig = buttons.html(),
            full = $('#editable-post-name-full');

        // Deal with Twemoji in the post-name.
        full.find( 'img' ).replaceWith( function() { return this.alt; } );
        full = full.html();

        permalink.html( permalinkInner );

        // Save current content to revert to when cancelling.
        $el = $( '#editable-post-name' );
        revert_e = $el.html();

        buttons.html( '<button type="button" class="save button button-small">'
            + postL10n.ok + '</button> <button type="button" class="cancel button- link">'
            + postL10n.cancel + '</button>' );


        // Save permalink changes.
        buttons.children( '.save' ).click( function() {
            var new_slug = $el.children( 'input' ).val();

            if ( new_slug == $('#editable-post-name-full').text() ) {
                buttons.children('.cancel').click();
                return;
            }

            $.post(
                ajaxurl,
                {
                    action: 'winp_permalink',
                    code_id: $('#post_ID').val(),
                    filetype: $('#wbcr_inp_snippet_type').val(),
                    new_slug: new_slug,
                    permalink: permalinkHref,
                    winp_permalink_nonce: $('#winp-permalink-nonce').val()
                },
                function(data) {
                    var box = $('#edit-slug-box');
                    box.html(data);
                    if (box.hasClass('hidden')) {
                        box.fadeIn('fast', function () {
                            box.removeClass('hidden');
                        });
                    }
                }
            );
        });

        // Cancel editing of permalink.
        buttons.children( '.cancel' ).click( function() {
            $('#view-post-btn').show();
            $el.html(revert_e);
            buttons.html(buttonsOrig);
            permalink.html(permalinkOrig);
            real_slug.val(revert_slug);
            $( '.winp-edit-slug' ).focus();
        });

        $el.html( '<input type="text" name="new_slug" id="new-post-slug" value="'
            + slug_value + '" autocomplete="off" />' )
        .children( 'input' )
        .keydown( function( e ) {
            var key = e.which;
            // On [enter], just save the new slug, don't save the post.
            if ( 13 === key ) {
                e.preventDefault();
                buttons.children( '.save' ).click();
            }
            // On [esc] cancel the editing.
            if ( 27 === key ) {
                buttons.children( '.cancel' ).click();
            }
        } ).keyup( function() {
            real_slug.val( this.value );
        }).focus();
    });

    if ($('.winp-field-premium-element').length > 0) {
        $('.winp-field-premium-element').wrap('<div class="winp-field-premium-icon"></div>');
    }

    // Handle click on shortcode input to copy to clipboard
    $('input.wbcr_inp_shortcode_input').on('click', function (e) {
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
            height: '38px',
            width: '100%',
            marginTop: '-10px',
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
