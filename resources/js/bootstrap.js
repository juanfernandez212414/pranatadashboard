// File konfigurasi dasar untuk mengatur library eksternal (seperti Axios) sebelum aplikasi dimuat.

import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
