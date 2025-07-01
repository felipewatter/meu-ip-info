document.addEventListener("DOMContentLoaded", function () {
    if (typeof meuIpInfoToken === "undefined" || meuIpInfoToken === "") {
        console.error("Token não configurado.");
        return;
    }

    fetch("https://ipinfo.io/json?token=" + meuIpInfoToken)
        .then(response => response.json())
        .then(data => {
            const [latitude, longitude] = data.loc.split(",");

            const ipEl = document.getElementById("ip");
            const orgEl = document.getElementById("org");
            const countryEl = document.getElementById("country");
            const regionEl = document.getElementById("region");
            const cityEl = document.getElementById("city");

            if (ipEl) ipEl.textContent = data.ip;
            if (orgEl) orgEl.textContent = data.org;
            if (countryEl) countryEl.textContent = data.country;
            if (regionEl) regionEl.textContent = data.region;
            if (cityEl) cityEl.textContent = data.city;

            var map = L.map('map').setView([latitude, longitude], 10);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
            }).addTo(map);

            L.marker([latitude, longitude])
                .addTo(map)
                .bindPopup("Você está aqui!")
                .openPopup();

            const infoBox = document.getElementById("ip-info");
            if (infoBox) {
                infoBox.classList.add("loaded");
            }
        })
        .catch(error => {
            console.error("Erro ao obter dados do IP:", error);
        });
});
