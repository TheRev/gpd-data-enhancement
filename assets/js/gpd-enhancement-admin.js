jQuery(document).ready(function($) {
    $('body').on('click', '.gpd-scrape-insights-button', function(e) {
        e.preventDefault();

        var $button = $(this);
        var postId = $button.data('postid');
        var nonce = $button.data('nonce');
        var $statusDiv = $('#gpd-scrape-status-' + postId);
        var $dataContainer = $('#gpd-scraped-data-container-' + postId);

        if ($button.prop('disabled')) {
            return;
        }

        $button.prop('disabled', true).text(gpdEnhancementAdmin.text_scraping);
        $statusDiv.text('').removeClass('notice-success notice-error').hide();
        $dataContainer.hide();
        $dataContainer.find('.scraped-page-title').text('');
        $dataContainer.find('.scraped-first-h1').text('');
        $dataContainer.find('.scraped-meta-description').text('');

        $.ajax({
            url: gpdEnhancementAdmin.ajax_url,
            type: 'POST',
            data: {
                action: 'gpd_enhancement_scrape_all_sources', // Updated action!
                post_id: postId,
                _ajax_nonce: nonce
            },
            success: function(response) {
                // console.log("Full AJAX Response:", response);

                if (response.success) {
                    $button.text(gpdEnhancementAdmin.text_done);

                    var serverMessage = (response.data && response.data.message) ? response.data.message : 'Operation successful.';
                    $statusDiv.text(serverMessage).removeClass('notice-error').addClass('notice-success').show();

                    // Multi-source results
                    var results = response.data && response.data.results;
                    var firstPopulated = false;

                    if (results && typeof results === 'object') {
                        // Prepare details summary for all sources
                        var details = '';
                        $.each(results, function(source, result) {
                            var msg = (result && result.message) ? result.message : '';
                            var ok = !!(result && result.success);
                            details += '<div style="margin-bottom:7px;"><strong>' + source.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase()) + ':</strong> ';
                            details += ok ? '<span style="color:green;">✔</span> ' : '<span style="color:red;">✖</span> ';
                            details += msg + '</div>';

                            // Populate the field preview from the first source with data
                            if (!firstPopulated && ok && result.data) {
                                $dataContainer.find('.scraped-page-title').text(result.data.page_title || 'Not found');
                                $dataContainer.find('.scraped-first-h1').text(result.data.first_h1 || 'Not found');
                                $dataContainer.find('.scraped-meta-description').text(result.data.meta_description || 'Not found');
                                firstPopulated = true;
                                $dataContainer.show();
                            }
                        });
                        $statusDiv.append('<div class="gpd-multisource-details" style="margin-top:8px;">' + details + '</div>');
                        if (!firstPopulated) {
                            $dataContainer.hide();
                        }
                    } else {
                        $statusDiv.append(' (No detailed source results found in the response)');
                    }
                } else {
                    $button.prop('disabled', false).text(gpdEnhancementAdmin.text_retry_button || 'Retry Scrape');
                    var errorMessage = (response.data && response.data.message) ? response.data.message : gpdEnhancementAdmin.text_error;
                    $statusDiv.text('Error: ' + errorMessage).removeClass('notice-success').addClass('notice-error').show();

                    // If there are per-source errors, display them
                    if (response.data && response.data.results) {
                        var details = '';
                        $.each(response.data.results, function(source, result) {
                            var msg = (result && result.message) ? result.message : '';
                            details += '<div style="margin-bottom:7px;"><strong>' + source.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase()) + ':</strong> ';
                            details += '<span style="color:red;">✖</span> ' + msg + '</div>';
                        });
                        $statusDiv.append('<div class="gpd-multisource-details">' + details + '</div>');
                    }
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                $button.prop('disabled', false).text(gpdEnhancementAdmin.text_retry_button || 'Retry Scrape');
                $statusDiv.text(gpdEnhancementAdmin.text_error + ': ' + textStatus + ' - ' + errorThrown).removeClass('notice-success').addClass('notice-error').show();
                console.error("GPD Enhancement AJAX Error:", textStatus, errorThrown, jqXHR.responseText);
            }
        });
    });
});