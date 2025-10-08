$(document).ready(function() {
    
    $('#equipment-search').on('keyup', function() {
        let query = $(this).val();
        $.get('', { q: query }, function(data){
            let html = $(data).find('#available-equipments').html();
            $('#available-equipments').html(html);
        });
    });

    $('#searchForm').submit(function(e) {
        e.preventDefault();
        let query = $('#equipment-search').val();
        window.location.href = '?q=' + encodeURIComponent(query);
    });

    $('#loginForm').submit(function(e) {
        e.preventDefault();
        
        const $btn = $('#loginBtn');
        const $message = $('#loginMessage');
        
        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i>Logging in...');
        $message.empty();
        
        $.post('', $(this).serialize() + '&action=login')
        .done(function(response) {
            if (response.status === 'success') {
                $message.html('<div class="alert alert-success"><i class="fas fa-check-circle me-2"></i>' + response.message + '</div>');
                setTimeout(function() {
                    window.location.href = response.redirect || 'user/dashboard.php';
                }, 1500);
            } else {
                $message.html('<div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i>' + response.message + '</div>');
            }
        })
        .fail(function() {
            $message.html('<div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i>Connection error. Please try again.</div>');
        })
        .always(function() {
            $btn.prop('disabled', false).html('<i class="fas fa-sign-in-alt me-2"></i>Login');
        });
    });

    $('#signupForm').submit(function(e) {
        e.preventDefault();
        
        const $btn = $('#signupBtn');
        const $message = $('#signupMessage');
        
        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i>Creating Account...');
        $message.empty();
        
        $.post('', $(this).serialize() + '&action=signup')
        .done(function(response) {
            if (typeof response === 'string') {
                if (response.trim() === 'success') {
                    $message.html('<div class="alert alert-success"><i class="fas fa-check-circle me-2"></i>Account created successfully!</div>');
                    setTimeout(function() {
                        $('#signupModal').modal('hide');
                        $('#loginModal').modal('show');
                        $('#signupForm')[0].reset();
                        $message.empty();
                    }, 2000);
                } else {
                    $message.html('<div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i>' + response + '</div>');
                }
            } else {
                if (response.status === 'success') {
                    $message.html('<div class="alert alert-success"><i class="fas fa-check-circle me-2"></i>' + response.message + '</div>');
                    setTimeout(function() {
                        $('#signupModal').modal('hide');
                        $('#loginModal').modal('show');
                        $('#signupForm')[0].reset();
                        $message.empty();
                    }, 2000);
                } else {
                    $message.html('<div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i>' + response.message + '</div>');
                }
            }
        })
        .fail(function() {
            $message.html('<div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i>Connection error. Please try again.</div>');
        })
        .always(function() {
            $btn.prop('disabled', false).html('<i class="fas fa-user-plus me-2"></i>Create Account');
        });
    });

    $('.modal').on('hidden.bs.modal', function() {
        $(this).find('.alert').remove();
        $(this).find('form')[0].reset();
    });
});

function showLoginPrompt() {
    $('#loginModal').modal('show');
}