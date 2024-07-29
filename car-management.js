jQuery(document).ready(function ($) {
    $('#car-entry-form').on('submit', function (event) {
        event.preventDefault();

        var formData = new FormData(this);
        formData.append('action', 'handle_car_entry');
        formData.append('car_entry_nonce', $('#car_entry_nonce').val());

        $.ajax({
            url: car_management.ajax_url,
            type: 'POST',
            data: formData,
            contentType: false,
            processData: false,
            success: function (response) {
                $('#car-name-error').text(''); 
                $('#form-message').text(''); 
                if (response.success) {
                    $('#form-message').css('color', 'green').text(response.data.message);
                    $('#car-entry-form')[0].reset();
                } else {
                    if (response.data.message === 'Car name already exists.') {
                        $('#car-name-error').text(response.data.message);
                    } else {
                        $('#form-message').css('color', 'red').text(response.data.message);
                    }
                }
            },
            error: function (xhr, status, error) {
                console.log(xhr.responseText); 
                $('#form-message').css('color', 'red').text('An error occurred.');
            }
        });
    });
});