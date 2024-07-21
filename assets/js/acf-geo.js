(function($) {
    function initializeAutocomplete() {
        $('.acf-field-google_map input').each(function() {
            var input = this;
            var autocomplete = new google.maps.places.Autocomplete(input);
            autocomplete.addListener('place_changed', function () {
                var place = autocomplete.getPlace();
                if (place.geometry) {
                    var lat = place.geometry.location.lat();
                    var lng = place.geometry.location.lng();
                    updateLatLngFields(input, lat, lng);
                }
            });
        });
    }

    function updateLatLngFields(input, lat, lng) {
        var $field = $(input).closest('.acf-field');
        var latField = $field.siblings('.acf-field[data-name="latitude"]').find('input');
        var lngField = $field.siblings('.acf-field[data-name="longitude"]').find('input');
        if (latField.length && lngField.length) {
            latField.val(lat);
            lngField.val(lng);
        }
    }

    $(document).ready(function() {
        if (typeof google !== 'undefined' && google.maps && google.maps.places) {
            initializeAutocomplete();
        } else {
            var script = document.createElement('script');
            script.src = 'https://maps.googleapis.com/maps/api/js?key=' + document.getElementById('google-maps-api-key').value + '&libraries=places&callback=initializeAutocomplete';
            script.async = true;
            document.head.appendChild(script);
        }
    });

    if (typeof acf !== 'undefined') {
        acf.add_action('google_map_init', function(map, marker, field) {
            google.maps.event.addListener(map, 'click', function(event) {
                var lat = event.latLng.lat();
                var lng = event.latLng.lng();
                updateLatLngFields(field.$el.find('input'), lat, lng);
            });

            google.maps.event.addListener(marker, 'dragend', function(event) {
                var lat = event.latLng.lat();
                var lng = event.latLng.lng();
                updateLatLngFields(field.$el.find('input'), lat, lng);
            });
        });

        acf.add_action('google_map_change', function(field) {
            var place = field.$el.find('input').getPlace();
            if (place.geometry) {
                var lat = place.geometry.location.lat();
                var lng = place.geometry.location.lng();
                updateLatLngFields(field.$el.find('input'), lat, lng);
            }
        });
    }
})(jQuery);
