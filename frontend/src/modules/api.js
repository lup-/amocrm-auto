import axios from "axios";

async function loadApiData(data, endpointUrl) {
    if (!endpointUrl) {
        endpointUrl = '/amo.php';
    }

    let response = await axios.get(endpointUrl, {
        params: data
    });
    return response.data;
}

export {loadApiData};