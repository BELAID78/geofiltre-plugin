// custom-scripts.js

function initializeAutocomplete() {
    var input = document.getElementById('address');
    var autocomplete = new google.maps.places.Autocomplete(input);
    autocomplete.addListener('place_changed', function () {
        var place = autocomplete.getPlace();
        if (place.geometry) {
            var lat = place.geometry.location.lat();
            var lng = place.geometry.location.lng();
            document.getElementById('latitude').value = lat;
            document.getElementById('longitude').value = lng;
        }
    });
}

function geolocateUser() {
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(function (position) {
            var lat = position.coords.latitude;
            var lng = position.coords.longitude;

            var geocoder = new google.maps.Geocoder();
            var latlng = { lat: lat, lng: lng };
            geocoder.geocode({ location: latlng }, function (results, status) {
                if (status === 'OK') {
                    if (results[0]) {
                        var input = document.getElementById('address');
                        input.value = results[0].formatted_address;
                        document.getElementById('latitude').value = lat;
                        document.getElementById('longitude').value = lng;
                    } else {
                        window.alert('No results found');
                    }
                } else {
                    window.alert('Geocoder failed due to: ' + status);
                }
            });
        });
    } else {
        alert('La géolocalisation n\'est pas prise en charge par votre navigateur.');
    }
}

document.addEventListener('DOMContentLoaded', function () {
    document.getElementById('geolocate').addEventListener('click', geolocateUser);
    document.getElementById('search-form').addEventListener('submit', function (e) {
        e.preventDefault();
        fetchResults();
    });
    if (typeof google !== 'undefined' && google.maps && google.maps.places) {
        initializeAutocomplete();
    } else {
        var script = document.createElement('script');
        script.src = 'https://maps.googleapis.com/maps/api/js?key=' + document.getElementById('google-maps-api-key').value + '&libraries=places&callback=initializeAutocomplete';
        script.async = true;
        document.head.appendChild(script);
    }
});

function fetchResults() {
    var address = document.getElementById('address').value;
    var lat = document.getElementById('latitude').value;
    var lng = document.getElementById('longitude').value;
    var radius = document.getElementById('radius').value;
    var searchButton = document.getElementById('search-button');
    searchButton.innerHTML = '<span class="loading-spinner"></span> Recherche en cours...';

    setTimeout(function() {  // Simulate a loading delay
        fetch(`/wp-json/geofiltre/v1/search?address=${encodeURIComponent(address)}&lat=${lat}&lng=${lng}&radius=${radius}`)
            .then(response => response.json())
            .then(data => {
                displayResults(data);
                searchButton.innerHTML = 'Rechercher';
            })
            .catch(error => {
                console.error('Error:', error);
                searchButton.innerHTML = 'Rechercher';
            });
    }, 3000); // 3 seconds delay
}

function displayResults(data) {
    var resultsContainer = document.getElementById('search-results');
    resultsContainer.innerHTML = '';

    if (data.length > 0) {
        data.forEach(item => {
            var resultItem = document.createElement('div');
            resultItem.className = 'search-result-item';
            resultItem.innerHTML = `<h2><a href="${item.link}">${item.title}</a></h2><p>Distance: ${item.distance.toFixed(2)} km</p>`;
            resultsContainer.appendChild(resultItem);
        });
    } else {
        resultsContainer.innerHTML = '<p>Aucun résultat trouvé.</p>';
    }
}
