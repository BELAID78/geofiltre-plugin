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
        fetchResults(1); // Fetch results for the first page
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

function fetchResults(page) {
    var address = document.getElementById('address').value;
    var lat = document.getElementById('latitude').value;
    var lng = document.getElementById('longitude').value;
    var radius = document.getElementById('radius').value;
    var searchButton = document.getElementById('search-button');
    searchButton.innerHTML = '<span class="loading-spinner"></span> Recherche en cours...';

    setTimeout(function() {  // Simulate a loading delay
        fetch(`/wp-json/geofiltre/v1/search?lat=${lat}&lng=${lng}&radius=${radius}&paged=${page}`)
            .then(response => response.json())
            .then(data => {
                displayResults(data.articles, data.total_pages, data.current_page, data.total_results);
                searchButton.innerHTML = 'Rechercher';
            })
            .catch(error => {
                console.error('Error:', error);
                searchButton.innerHTML = 'Rechercher';
            });
    }, 3000); // 3 seconds delay
}

function displayResults(data, totalPages, currentPage, totalResults) {
    var resultsContainer = document.getElementById('search-results');
    var resultsMessageContainer = document.querySelector('.results-message-container');

    // Remove old results found message
    if (resultsMessageContainer) {
        resultsMessageContainer.remove();
    }

    resultsContainer.innerHTML = '';

    var resultsFoundText = geofiltreSettings.resultsFoundText.replace('%d', totalResults);
    var noResultsText = geofiltreSettings.noResultsText;

    resultsMessageContainer = document.createElement('div');
    resultsMessageContainer.className = 'results-message-container';

    if (totalResults > 0) {
        var resultsFoundMessage = document.createElement('div');
        resultsFoundMessage.className = 'results-found-message';
        resultsFoundMessage.textContent = resultsFoundText;
        resultsMessageContainer.appendChild(resultsFoundMessage);

        data.forEach(item => {
            var resultItem = document.createElement('article');
            resultItem.className = 'search-result-item elementor-post elementor-grid-item';
            
            var resultContent = `
                <div class="elementor-post__card">
                    <div class="elementor-post__thumbnail__wrapper">
                        <a class="elementor-post__thumbnail__link" href="${item.link}">
                            <div class="elementor-post__thumbnail">
                                <img src="${item.thumbnail}" alt="${item.title}" style="width: ${geofiltreSettings.thumbnailSize}; height: ${geofiltreSettings.thumbnailSize};">
                            </div>
                        </a>`;
                        
            if (geofiltreSettings.showAvatar) {
                resultContent += `
                    <div class="elementor-post__avatar">
                        <img alt="${item.author}" src="https://secure.gravatar.com/avatar/?s=128&d=mm&r=g" class="avatar avatar-128 photo">
                    </div>`;
            }
            
            resultContent += `</div>
                    <div class="elementor-post__text">
                        <h3 class="elementor-post__title">
                            <a href="${item.link}" style="color: ${geofiltreSettings.resultTextColor};">${item.title}</a>
                        </h3>
                        <div class="elementor-post__excerpt">
                            <p>${item.excerpt}</p>
                        </div>
                        <a class="elementor-post__read-more" href="${item.link}">${geofiltreSettings.readMoreText}</a>
                    </div>
                    <div class="elementor-post__meta-data">
                        <span class="elementor-post-date">${item.date}</span>
                        <span class="elementor-post-avatar">${item.reviews} Comments</span>
                    </div>
                </div>
            `;

            resultItem.innerHTML = resultContent;
            resultsContainer.appendChild(resultItem);
        });

        var pagination = createPagination(totalPages, currentPage);
        resultsContainer.appendChild(pagination);
    } else {
        resultsMessageContainer.innerHTML = `<p>${noResultsText}</p>`;
    }

    document.getElementById('search-results-container').prepend(resultsMessageContainer);
}

function createPagination(totalPages, currentPage) {
    var paginationContainer = document.createElement('div');
    paginationContainer.className = 'pagination';

    for (var i = 1; i <= totalPages; i++) {
        var pageButton = document.createElement('button');
        pageButton.textContent = i;
        pageButton.className = i === currentPage ? 'active' : '';
        pageButton.addEventListener('click', function() {
            fetchResults(this.textContent);
        });
        paginationContainer.appendChild(pageButton);
    }

    return paginationContainer;
}
