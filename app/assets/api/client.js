import axios from 'axios';

const api = axios.create({
    baseURL: '/api',
    withCredentials: true,
    headers: { 'Accept': 'application/json' },
});

api.interceptors.response.use(
    (res) => res,
    (err) => {
        if (err.response?.status === 401 && !err.config?._silent) {
            // notify auth store via event so router can react
            window.dispatchEvent(new CustomEvent('rcm:unauthorized'));
        }
        return Promise.reject(err);
    },
);

export default api;
