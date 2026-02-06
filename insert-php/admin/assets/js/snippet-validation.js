/**
 * Snippet validation before publish/update
 */
(function($) {
    'use strict';

    let validationInProgress = false;
    let validationPassed = false;

    /**
     * Validate snippet code via AJAX
     */
    function validateSnippetCode(callback) {
        const postId = $('#post_ID').val();
        
        // Get snippet code from CodeMirror if available, otherwise from textarea
        let snippetCode = $('#post_content').val();
        if (wp && wp.codeEditor && wp.codeEditor.instances && wp.codeEditor.instances.post_content) {
            const editor = wp.codeEditor.instances.post_content;
            if (editor && editor.codemirror) {
                snippetCode = editor.codemirror.getValue();
            }
        }
        
        const snippetType = $('#wbcr_inp_snippet_type').val();

        // Show validation indicator
        const $publishButton = $('#publish');
        const originalText = $publishButton.val();
        const originalClasses = $publishButton.attr('class');
        $publishButton.val(wbcrInpValidation.validatingText).prop('disabled', true);

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'wbcr_inp_ajax_validate_snippet',
                post_id: postId,
                snippet_code: snippetCode,
                snippet_type: snippetType,
                nonce: wbcrInpValidation.nonce
            },
            success: function(response) {
                // Restore button state
                $publishButton.prop('disabled', false);
                $publishButton.val(originalText);
                if (originalClasses) {
                    $publishButton.attr('class', originalClasses);
                }
                
                if (response.success) {
                    callback(true);
                } else {
                    showValidationError(response.data.message);
                    callback(false);
                }
            },
            error: function(xhr) {
                // Restore button state
                $publishButton.prop('disabled', false);
                $publishButton.val(originalText);
                if (originalClasses) {
                    $publishButton.attr('class', originalClasses);
                }
                
                let errorMessage = wbcrInpValidation.errorText;
                if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                    errorMessage = xhr.responseJSON.data.message;
                }
                
                showValidationError(errorMessage);
                callback(false);
            }
        });
    }

    /**
     * Show validation error message
     */
    function showValidationError(message) {
        // Remove any existing error notices
        $('.winp-validation-error').remove();

        // Create error notice
        const $notice = $('<div>', {
            class: 'notice notice-error is-dismissible winp-validation-error',
            html: '<p><strong>' + wbcrInpValidation.errorTitle + '</strong><br>' + message + '</p>'
        });

        // Add dismiss button functionality
        $notice.append('<button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button>');

        // Insert after page title or at the top of the form
        if ($('#wpbody-content .wrap h1').length) {
            $notice.insertAfter('#wpbody-content .wrap h1');
        } else {
            $('#post').prepend($notice);
        }

        // Scroll to the error
        $('html, body').animate({
            scrollTop: $notice.offset().top - 100
        }, 500);

        // Handle dismiss button
        $notice.on('click', '.notice-dismiss', function() {
            $notice.fadeOut(300, function() {
                $(this).remove();
            });
        });
    }

    /**
     * Intercept form submission
     */
    function interceptFormSubmission() {
        $('#post').on('submit', function(e) {
            const $form = $(this);
            const snippetType = $('#wbcr_inp_snippet_type').val();
            const snippetScope = $('input[name="wbcr_inp_snippet_scope"]:checked').val();

            // Only validate PHP and Universal snippets with evrywhere or auto scope
            const needsValidation = (snippetType === 'php' || snippetType === 'universal') &&
                (snippetScope === 'evrywhere' || snippetScope === 'auto');

            if (!needsValidation) {
                return true; // Allow form submission
            }

            // If validation already passed, allow submission
            if (validationPassed) {
                validationPassed = false; // Reset for next time
                return true;
            }

            // If validation in progress, prevent submission
            if (validationInProgress) {
                e.preventDefault();
                return false;
            }

            // Prevent form submission and validate
            e.preventDefault();
            validationInProgress = true;

            validateSnippetCode(function(isValid) {
                validationInProgress = false;
                
                if (isValid) {
                    validationPassed = true;
                    // Remove any error notices
                    $('.winp-validation-error').remove();
                    // Submit the form
                    $form.off('submit').submit();
                }
                // If not valid, error is already shown, just stop here
            });

            return false;
        });
    }

    /**
     * Initialize validation on document ready
     */
    $(document).ready(function() {
        // Check if we're on the right page
        const postType = $('#post_type').val();
        
        // Only initialize on snippet edit pages
        if (typeof wbcrInpValidation !== 'undefined' && postType === wbcrInpValidation.postType) {
            interceptFormSubmission();
        } else {
            console.warn('Snippet Validation: Not initializing - wrong page type or config not loaded');
        }
    });

})(jQuery);
