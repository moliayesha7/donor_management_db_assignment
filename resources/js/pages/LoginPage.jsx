import React from 'react';
import { useDispatch } from 'react-redux';
import { Card, Form, Input, Button, Typography, message, Alert } from 'antd';
import { UserOutlined, LockOutlined } from '@ant-design/icons';
import { useLoginMutation } from '../store/apiSlice.js';
import { setCredentials } from '../store/authSlice.js';

const { Title, Text } = Typography;

export default function LoginPage() {
    const dispatch = useDispatch();
    const [login, { isLoading, error }] = useLoginMutation();

    const onFinish = async (values) => {
        try {
            const res = await login(values).unwrap();
            dispatch(setCredentials({ token: res.data.token, user: res.data.user }));
            message.success(res.message || 'Welcome back!');
        } catch (err) {
            message.error(
                err?.data?.errors?.email?.[0] ||
                err?.data?.message ||
                'Login failed. Please check your credentials.'
            );
        }
    };

    const serverError =
        error?.data?.errors?.email?.[0] || error?.data?.message;

    return (
        <div
            style={{
                minHeight: '100vh',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                background: 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
                padding: 16,
            }}
        >
            <Card style={{ width: '100%', maxWidth: 420, boxShadow: '0 10px 30px rgba(0,0,0,0.2)' }}>
                <div style={{ textAlign: 'center', marginBottom: 24 }}>
                    <Title level={3} style={{ marginBottom: 4 }}>Donor Management</Title>
                    <Text type="secondary">Sign in to continue</Text>
                </div>

                {serverError && (
                    <Alert
                        type="error"
                        message={serverError}
                        showIcon
                        style={{ marginBottom: 16 }}
                    />
                )}

                <Form
                    layout="vertical"
                    onFinish={onFinish}
                    initialValues={{ email: 'admin@gmail.com', password: '' }}
                    requiredMark={false}
                >
                    <Form.Item
                        name="email"
                        label="Email"
                        rules={[
                            { required: true, message: 'Please enter your email' },
                            { type: 'email', message: 'Please enter a valid email' },
                        ]}
                    >
                        <Input
                            prefix={<UserOutlined />}
                            placeholder="you@example.com"
                            size="large"
                            autoComplete="email"
                        />
                    </Form.Item>

                    <Form.Item
                        name="password"
                        label="Password"
                        rules={[
                            { required: true, message: 'Please enter your password' },
                            { min: 6, message: 'Minimum 6 characters' },
                        ]}
                    >
                        <Input.Password
                            prefix={<LockOutlined />}
                            placeholder="••••••••"
                            size="large"
                            autoComplete="current-password"
                        />
                    </Form.Item>

                    <Form.Item style={{ marginBottom: 8 }}>
                        <Button
                            type="primary"
                            htmlType="submit"
                            block
                            size="large"
                            loading={isLoading}
                        >
                            Sign In
                        </Button>
                    </Form.Item>

                    <div style={{ textAlign: 'center' }}>
                        <Text type="secondary" style={{ fontSize: 12 }}>
                            Default seed: admin@gmail.com / 12345678
                        </Text>
                    </div>
                </Form>
            </Card>
        </div>
    );
}
