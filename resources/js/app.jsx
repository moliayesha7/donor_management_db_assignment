import './bootstrap';
import React, { useEffect } from 'react';
import { createRoot } from 'react-dom/client';
import { Provider, useDispatch, useSelector } from 'react-redux';
import { ConfigProvider, App as AntApp } from 'antd';
import { store } from './store/index.js';
import { useLazyMeQuery } from './store/apiSlice.js';
import { setUser, clearCredentials, selectIsAuthenticated, selectToken } from './store/authSlice.js';

import LoginPage from './pages/LoginPage.jsx';
import AppShell from './layouts/AppShell.jsx';

function AuthGate() {
    const isAuthed = useSelector(selectIsAuthenticated);
    const token = useSelector(selectToken);
    const dispatch = useDispatch();
    const [fetchMe] = useLazyMeQuery();

    useEffect(() => {
        if (!token) return;
        fetchMe()
            .unwrap()
            .then((res) => {
                if (res?.data) dispatch(setUser(res.data));
            })
            .catch(() => {
                dispatch(clearCredentials());
            });
    }, [token, fetchMe, dispatch]);

    return isAuthed ? <AppShell /> : <LoginPage />;
}

function Root() {
    return (
        <Provider store={store}>
            <ConfigProvider theme={{ token: { colorPrimary: '#1677ff' } }}>
                <AntApp>
                    <AuthGate />
                </AntApp>
            </ConfigProvider>
        </Provider>
    );
}

const container = document.getElementById('root');
if (container) {
    const root = createRoot(container);
    root.render(<Root />);
}
