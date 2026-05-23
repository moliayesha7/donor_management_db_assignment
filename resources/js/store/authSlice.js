import { createSlice } from '@reduxjs/toolkit';

const TOKEN_KEY = 'dm_token';
const USER_KEY = 'dm_user';

const loadInitialState = () => {
    try {
        const token = localStorage.getItem(TOKEN_KEY);
        const userRaw = localStorage.getItem(USER_KEY);
        return {
            token: token || null,
            user: userRaw ? JSON.parse(userRaw) : null,
        };
    } catch {
        return { token: null, user: null };
    }
};

const authSlice = createSlice({
    name: 'auth',
    initialState: loadInitialState(),
    reducers: {
        setCredentials: (state, { payload }) => {
            state.token = payload.token;
            state.user = payload.user;
            try {
                localStorage.setItem(TOKEN_KEY, payload.token);
                localStorage.setItem(USER_KEY, JSON.stringify(payload.user));
            } catch {}
        },
        setUser: (state, { payload }) => {
            state.user = payload;
            try {
                localStorage.setItem(USER_KEY, JSON.stringify(payload));
            } catch {}
        },
        clearCredentials: (state) => {
            state.token = null;
            state.user = null;
            try {
                localStorage.removeItem(TOKEN_KEY);
                localStorage.removeItem(USER_KEY);
            } catch {}
        },
    },
});

export const { setCredentials, setUser, clearCredentials } = authSlice.actions;
export default authSlice.reducer;

export const selectToken = (state) => state.auth.token;
export const selectUser = (state) => state.auth.user;
export const selectIsAuthenticated = (state) => Boolean(state.auth.token);
