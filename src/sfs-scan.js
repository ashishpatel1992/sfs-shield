jQuery(document).ready(function($) {
    // Handle scan users button click
    $('#sfs-scan-users-btn').on('click', function(e) {
        e.preventDefault();
        $('#sfs-progress-bar').show();
        $('#sfs-progress-fill').css('width', '0%');
        $('#sfs-progress-text').text('Scanning: 0 of 0 users (0%)');

        function scanUsers(offset = 0) {
            $.ajax({
                url: sfsScan.ajax_url,
                type: 'POST',
                data: {
                    action: 'sfs_scan_users',
                    nonce: sfsScan.scan_nonce,
                    offset: offset
                },
                success: function(response) {
                    if (response.success && response.data) {
                        var progress = response.data.progress;
                        var processed = response.data.processed;
                        var total = response.data.total;

                        $('#sfs-progress-fill').css('width', progress + '%');
                        $('#sfs-progress-text').text('Scanning: ' + processed + ' of ' + total + ' users (' + Math.round(progress) + '%)');

                        if (!response.data.complete) {
                            scanUsers(offset + 5); // Continue with next batch
                        } else {
                            // Reload page to show results
                            window.location.reload();
                        }
                    } else {
                        $('#sfs-delete-messages').html('<div class="notice notice-error"><p>Scan failed: ' + (response.data.message || 'Unknown error') + '</p></div>');
                    }
                },
                error: function() {
                    $('#sfs-delete-messages').html('<div class="notice notice-error"><p>Scan failed: Server error</p></div>');
                }
            });
        }

        scanUsers();
    });

    // Handle individual delete button click
    $(document).on('click', '.sfs-delete-user', function(e) {
        e.preventDefault();
        var userId = $(this).data('user-id');
        var $row = $(this).closest('tr');
        var $messages = $('#sfs-delete-messages');

        $messages.empty();

        $.ajax({
            url: sfsScan.ajax_url,
            type: 'POST',
            data: {
                action: 'sfs_delete_users',
                nonce: sfsScan.nonce,
                user_ids: [userId]
            },
            success: function(response) {
                if (response.success) {
                    $row.remove();
                    $messages.html('<div class="notice notice-success"><p>' + response.data.message + '</p></div>');
                } else {
                    $messages.html('<div class="notice notice-error"><p>' + response.data.message + '</p></div>');
                }
            },
            error: function() {
                $messages.html('<div class="notice notice-error"><p>Deletion failed: Server error</p></div>');
            }
        });
    });

    // Handle delete selected users form submission
    $('#sfs-delete-users-form').on('submit', function(e) {
        e.preventDefault();
        var userIds = [];
        $(this).find('input[name="sfs_delete_users[]"]:checked').each(function() {
            userIds.push($(this).val());
        });
        var $messages = $('#sfs-delete-messages');

        $messages.empty();

        if (userIds.length === 0) {
            $messages.html('<div class="notice notice-error"><p>No users selected for deletion.</p></div>');
            return;
        }

        $.ajax({
            url: sfsScan.ajax_url,
            type: 'POST',
            data: {
                action: 'sfs_delete_users',
                nonce: sfsScan.nonce,
                user_ids: userIds
            },
            success: function(response) {
                if (response.success) {
                    response.data.deleted_ids.forEach(function(userId) {
                        $('tr[data-user-id="' + userId + '"]').remove();
                    });
                    $messages.html('<div class="notice notice-success"><p>' + response.data.message + '</p></div>');
                } else {
                    $messages.html('<div class="notice notice-error"><p>' + response.data.message + '</p></div>');
                }
            },
            error: function() {
                $messages.html('<div class="notice notice-error"><p>Deletion failed: Server error</p></div>');
            }
        });
    });
});