/**
 * Uninstall Prompt Handler
 *
 * Shows a confirmation dialog when the user attempts to delete the plugin,
 * allowing them to choose whether to keep downloaded fonts.
 */
(function($) {
    'use strict';

    // Only run on the plugins page
    if (typeof pagenow === 'undefined' || pagenow !== 'plugins') {
        return;
    }

    // Find the delete link for our plugin
    const pluginSlug = 'dwt-localfonts';
    const deleteLink = $(`tr[data-slug="${pluginSlug}"] .delete a`);

    if (!deleteLink.length) {
        return;
    }

    // Store the original href
    const originalHref = deleteLink.attr('href');

    // Prevent default delete action
    deleteLink.on('click', function(e) {
        e.preventDefault();
        showUninstallDialog(originalHref);
    });

    /**
     * Show the uninstall confirmation dialog
     *
     * @param {string} deleteUrl The original delete URL
     */
    function showUninstallDialog(deleteUrl) {
        const dialog = `
            <div id="dwt-uninstall-dialog" class="dwt-modal-overlay">
                <div class="dwt-modal">
                    <div class="dwt-modal-header">
                        <h2>Uninstall Local Font Manager</h2>
                    </div>
                    <div class="dwt-modal-content">
                        <p><strong>You are about to delete the Local Font Manager plugin.</strong></p>
                        <p>What would you like to do with your downloaded font files?</p>

                        <div class="dwt-uninstall-options">
                            <label class="dwt-option-card">
                                <input type="radio" name="dwt_keep_fonts" value="0" checked>
                                <div class="dwt-option-content">
                                    <strong>Delete Everything</strong>
                                    <p>Remove all plugin data including downloaded fonts (recommended for clean uninstall)</p>
                                </div>
                            </label>

                            <label class="dwt-option-card">
                                <input type="radio" name="dwt_keep_fonts" value="1">
                                <div class="dwt-option-content">
                                    <strong>Keep Downloaded Fonts</strong>
                                    <p>Preserve font files in wp-content/uploads/dwt-local-fonts/ (useful if you plan to reinstall)</p>
                                </div>
                            </label>
                        </div>
                    </div>
                    <div class="dwt-modal-footer">
                        <button type="button" class="button button-secondary dwt-cancel">Cancel</button>
                        <button type="button" class="button button-primary dwt-confirm-delete">
                            <span class="dwt-button-text">Delete Plugin</span>
                            <span class="spinner" style="display: none;"></span>
                        </button>
                    </div>
                </div>
            </div>
        `;

        // Add dialog to page
        $('body').append(dialog);

        // Handle cancel
        $('.dwt-cancel').on('click', function() {
            $('#dwt-uninstall-dialog').remove();
        });

        // Handle confirm
        $('.dwt-confirm-delete').on('click', function() {
            const keepFonts = $('input[name="dwt_keep_fonts"]:checked').val();
            const button = $(this);
            const buttonText = button.find('.dwt-button-text');
            const spinner = button.find('.spinner');

            // Show loading state
            button.prop('disabled', true);
            buttonText.text('Saving preference...');
            spinner.css('display', 'inline-block').css('visibility', 'visible').css('float', 'none');

            // Save preference via AJAX
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'dwt_save_uninstall_preference',
                    nonce: dwtUninstallPrompt.nonce,
                    keep_fonts: keepFonts
                },
                success: function(response) {
                    if (response.success) {
                        // Redirect to delete URL
                        window.location.href = deleteUrl;
                    } else {
                        alert('Failed to save preference. Please try again.');
                        button.prop('disabled', false);
                        buttonText.text('Delete Plugin');
                        spinner.hide();
                    }
                },
                error: function() {
                    alert('An error occurred. Please try again.');
                    button.prop('disabled', false);
                    buttonText.text('Delete Plugin');
                    spinner.hide();
                }
            });
        });

        // Close on overlay click
        $('.dwt-modal-overlay').on('click', function(e) {
            if ($(e.target).hasClass('dwt-modal-overlay')) {
                $('#dwt-uninstall-dialog').remove();
            }
        });

        // Close on escape key
        $(document).on('keydown.dwtUninstall', function(e) {
            if (e.key === 'Escape') {
                $('#dwt-uninstall-dialog').remove();
                $(document).off('keydown.dwtUninstall');
            }
        });
    }

})(jQuery);
