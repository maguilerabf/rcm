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
            console.warn('[RCM api] 401 from', err.config?.url, '→ dispatch rcm:unauthorized');
            window.dispatchEvent(new CustomEvent('rcm:unauthorized', {
                detail: { url: err.config?.url },
            }));
        }
        return Promise.reject(err);
    },
);

export default api;
