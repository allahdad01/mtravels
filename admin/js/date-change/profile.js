 // Listen for form submission (using submit event)
 document.getElementById('updateProfileForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const newPassword = document.getElementById('newPassword').value;
    const confirmPassword = document.getElementById('confirmPassword').value;
    const currentPassword = document.getElementById('currentPassword').value;

    // If any password field is filled, all password fields must be filled
    if (newPassword || confirmPassword || currentPassword) {
        if (!currentPassword) {
            alert('please_enter_your_current_password');
            return;
        }
        if (!newPassword) {
            alert('please_enter_a_new_password');
            return;
        }
        if (!confirmPassword) {
            alert('please_confirm_your_new_password');
            return;
        }
        if (newPassword !== confirmPassword) {
            alert('new_passwords_do_not_match');
            return;
        }
        if (newPassword.length < 6) {
            alert('new_password_must_be_at_least_6_characters_long');
            return;
        }
    }
    
    const formData = new FormData(this);
    
    fetch('update_client_profile.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            // Clear password fields
            document.getElementById('currentPassword').value = '';
            document.getElementById('newPassword').value = '';
            document.getElementById('confirmPassword').value = '';
            location.reload();
        } else {
            alert(data.message || 'failed_to_update_profile');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('an_error_occurred_while_updating_the_profile');
    });
});


function previewImage(input) {
if (input.files && input.files[0]) {
    const reader = new FileReader();
    reader.onload = function(e) {
        document.getElementById('profilePreview').src = e.target.result;
    }
    reader.readAsDataURL(input.files[0]);
}
}