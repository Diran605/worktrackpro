import axios from 'axios';

const api = axios.create({
    baseURL: '/api/v1',
    headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json'
    }
});

// Inject the Sanctum bearer token into every request
api.interceptors.request.use(config => {
    const token = localStorage.getItem('access_token');
    if (token) {
        config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
});

// Expired or revoked token: clear it and send the user to the login page
api.interceptors.response.use(
    response => response,
    error => {
        const isAuthCall = error.config?.url?.startsWith('/auth/login');
        if (error.response?.status === 401 && !isAuthCall) {
            localStorage.removeItem('access_token');
            if (window.location.pathname !== '/staff/login') {
                window.location.assign('/staff/login');
            }
        }
        return Promise.reject(error);
    }
);

export default api;
