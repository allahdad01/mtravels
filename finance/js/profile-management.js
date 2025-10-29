document.addEventListener('DOMContentLoaded', function() {
    // Profile image preview
    const profileImage = document.getElementById('profileImage');
    if (profileImage) {
        profileImage.addEventListener('change', function() {
            previewImage(this);
        });
    }

    // Listen for form submission
    const updateProfileForm = document.getElementById('updateProfileForm');
    if (updateProfileForm) {
        updateProfileForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const newPassword = document.getElementById('newPassword').value;
            const confirmPassword = document.getElementById('confirmPassword').value;
            const currentPassword = document.getElementById('currentPassword').value;

            // If any password field is filled, all password fields must be filled
            if (newPassword || confirmPassword || currentPassword) {
                if (!currentPassword) {
                    alert('Please enter your current password');
                    return;
                }
                if (!newPassword) {
                    alert('Please enter a new password');
                    return;
                }
                if (!confirmPassword) {
                    alert('Please confirm your new password');
                    return;
                }
                if (newPassword !== confirmPassword) {
                    alert('New passwords do not match');
                    return;
                }
                if (newPassword.length < 6) {
                    alert('New password must be at least 6 characters long');
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
                    alert(data.message || 'Failed to update profile');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while updating the profile');
            });
        });
    }
});

// Function to preview selected profile image
function previewImage(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('profilePreview').src = e.target.result;
        }
        reader.readAsDataURL(input.files[0]);
    }
} 