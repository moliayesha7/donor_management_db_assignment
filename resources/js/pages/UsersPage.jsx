import React, { useState, useEffect } from 'react';
import { useSelector } from 'react-redux';
import {
    Table, Card, Button, Spin, Alert, Modal, Form, Input,
    Select, message, Space, Popconfirm, Tag, Row, Col, Tooltip,
} from 'antd';
import {
    PlusOutlined, EditOutlined, DeleteOutlined, SearchOutlined,
    UserOutlined, MailOutlined, LockOutlined,
} from '@ant-design/icons';
import {
    useGetUsersQuery,
    useGetRolesQuery,
    useCreateUserMutation,
    useUpdateUserMutation,
    useDeleteUserMutation,
} from '../store/apiSlice.js';
import { usePermissions } from '../utils/permissions.js';
import { selectUser } from '../store/authSlice.js';

const ROLE_COLORS = {
    super_admin: 'magenta',
    admin: 'blue',
    accountant: 'gold',
    user: 'green',
};

export default function UsersPage() {
    const { can } = usePermissions();
    const currentUser = useSelector(selectUser);

    const [filters, setFilters] = useState({ search: '', role_id: '', status: '' });
    const { data, error, isLoading, isFetching } = useGetUsersQuery(filters);
    const { data: rolesData, isLoading: isRolesLoading } = useGetRolesQuery();
    const [createUser, { isLoading: isCreating }] = useCreateUserMutation();
    const [updateUser, { isLoading: isUpdating }] = useUpdateUserMutation();
    const [deleteUser] = useDeleteUserMutation();

    const [isModalOpen, setIsModalOpen] = useState(false);
    const [editing, setEditing] = useState(null);
    const [form] = Form.useForm();
    const isEditing = Boolean(editing);

    useEffect(() => {
        if (!isModalOpen) return;
        if (editing) {
            form.setFieldsValue({
                name: editing.name,
                email: editing.email,
                role_id: editing.role_id,
                status: editing.status,
                password: '',
                password_confirmation: '',
            });
        } else {
            form.resetFields();
            form.setFieldsValue({ status: 'active' });
        }
    }, [isModalOpen, editing, form]);

    const openCreate = () => { setEditing(null); setIsModalOpen(true); };
    const openEdit = (row) => { setEditing(row); setIsModalOpen(true); };
    const closeModal = () => { setIsModalOpen(false); setEditing(null); form.resetFields(); };

    const handleFinish = async (values) => {
        const payload = { ...values };
        // Don't send empty password on edit
        if (isEditing && !payload.password) {
            delete payload.password;
            delete payload.password_confirmation;
        }
        try {
            if (isEditing) {
                await updateUser({ id: editing.id, ...payload }).unwrap();
                message.success('User updated successfully!');
            } else {
                await createUser(payload).unwrap();
                message.success('User created successfully!');
            }
            closeModal();
        } catch (err) {
            const errors = err?.data?.errors;
            if (errors) {
                const firstField = Object.keys(errors)[0];
                message.error(errors[firstField][0]);
            } else {
                message.error(err?.data?.message || `Failed to ${isEditing ? 'update' : 'create'} user`);
            }
        }
    };

    const handleDelete = async (id) => {
        try {
            await deleteUser(id).unwrap();
            message.success('User deleted');
        } catch (err) {
            message.error(err?.data?.message || 'Failed to delete user');
        }
    };

    const baseColumns = [
        { title: 'ID', dataIndex: 'id', key: 'id', width: 60 },
        { title: 'Name', dataIndex: 'name', key: 'name' },
        { title: 'Email', dataIndex: 'email', key: 'email' },
        {
            title: 'Role',
            key: 'role',
            render: (_, r) => (
                <Tag color={ROLE_COLORS[r?.role?.name] || 'default'}>
                    {r?.role?.name || '—'}
                </Tag>
            ),
        },
        {
            title: 'Status',
            dataIndex: 'status',
            key: 'status',
            render: (s) => <Tag color={s === 'active' ? 'green' : 'red'}>{s}</Tag>,
        },
    ];

    const actionColumn = (can('users.edit') || can('users.delete')) ? [{
        title: 'Actions',
        key: 'actions',
        width: 200,
        render: (_, record) => {
            const isSelf = currentUser?.id === record.id;
            return (
                <Space>
                    {can('users.edit') && (
                        <Button size="small" icon={<EditOutlined />} onClick={() => openEdit(record)}>Edit</Button>
                    )}
                    {can('users.delete') && (
                        <Tooltip title={isSelf ? "You can't delete your own account" : ''}>
                            <Popconfirm
                                title="Delete this user?"
                                description="This will also revoke all their tokens."
                                okText="Delete"
                                okButtonProps={{ danger: true }}
                                cancelText="Cancel"
                                onConfirm={() => handleDelete(record.id)}
                                disabled={isSelf}
                            >
                                <Button size="small" danger icon={<DeleteOutlined />} disabled={isSelf}>
                                    Delete
                                </Button>
                            </Popconfirm>
                        </Tooltip>
                    )}
                </Space>
            );
        },
    }] : [];

    const columns = [...baseColumns, ...actionColumn];

    if (isLoading) return <div style={{ textAlign: 'center', marginTop: 50 }}><Spin size="large" /></div>;
    if (error) return <Alert message="Error" description="Failed to load users" type="error" showIcon />;

    return (
        <Card
            title="Users & Roles"
            extra={can('users.create') && (
                <Button type="primary" icon={<PlusOutlined />} onClick={openCreate}>Add User</Button>
            )}
        >
            <Row gutter={[12, 12]} style={{ marginBottom: 16 }}>
                <Col xs={24} sm={10} md={8}>
                    <Input
                        prefix={<SearchOutlined />}
                        placeholder="Search by name or email"
                        allowClear
                        value={filters.search}
                        onChange={(e) => setFilters((f) => ({ ...f, search: e.target.value }))}
                    />
                </Col>
                <Col xs={12} sm={7} md={5}>
                    <Select
                        placeholder="Role"
                        allowClear
                        style={{ width: '100%' }}
                        loading={isRolesLoading}
                        value={filters.role_id || undefined}
                        onChange={(v) => setFilters((f) => ({ ...f, role_id: v || '' }))}
                        options={(rolesData?.data || []).map((r) => ({ value: r.id, label: r.name }))}
                    />
                </Col>
                <Col xs={12} sm={7} md={5}>
                    <Select
                        placeholder="Status"
                        allowClear
                        style={{ width: '100%' }}
                        value={filters.status || undefined}
                        onChange={(v) => setFilters((f) => ({ ...f, status: v || '' }))}
                        options={[
                            { value: 'active', label: 'Active' },
                            { value: 'inactive', label: 'Inactive' },
                        ]}
                    />
                </Col>
                <Col>
                    <Button onClick={() => setFilters({ search: '', role_id: '', status: '' })}>Reset</Button>
                </Col>
            </Row>

            <Table
                dataSource={data?.data || []}
                columns={columns}
                rowKey="id"
                loading={isFetching}
                scroll={{ x: 'max-content' }}
            />

            <Modal
                title={isEditing ? `Edit User: ${editing?.name}` : 'Create New User'}
                open={isModalOpen}
                onCancel={closeModal}
                footer={null}
                destroyOnClose
                width={520}
            >
                <Form form={form} layout="vertical" onFinish={handleFinish} style={{ marginTop: 20 }}>
                    <Form.Item
                        name="name"
                        label="Full Name"
                        rules={[{ required: true, message: 'Please enter name' }]}
                    >
                        <Input prefix={<UserOutlined />} placeholder="e.g., Rahim Ahmed" />
                    </Form.Item>

                    <Form.Item
                        name="email"
                        label="Email"
                        rules={[
                            { required: true, message: 'Please enter email' },
                            { type: 'email', message: 'Invalid email' },
                        ]}
                    >
                        <Input prefix={<MailOutlined />} placeholder="user@example.com" />
                    </Form.Item>

                    <Row gutter={12}>
                        <Col xs={24} sm={14}>
                            <Form.Item
                                name="role_id"
                                label="Role"
                                rules={[{ required: true, message: 'Please select role' }]}
                            >
                                <Select placeholder="Select role" loading={isRolesLoading}>
                                    {(rolesData?.data || []).map((r) => (
                                        <Select.Option key={r.id} value={r.id}>{r.name}</Select.Option>
                                    ))}
                                </Select>
                            </Form.Item>
                        </Col>
                        <Col xs={24} sm={10}>
                            <Form.Item
                                name="status"
                                label="Status"
                                rules={[{ required: true, message: 'Please select status' }]}
                            >
                                <Select>
                                    <Select.Option value="active">Active</Select.Option>
                                    <Select.Option value="inactive">Inactive</Select.Option>
                                </Select>
                            </Form.Item>
                        </Col>
                    </Row>

                    <Form.Item
                        name="password"
                        label={isEditing ? 'New Password (leave blank to keep current)' : 'Password'}
                        rules={isEditing ? [
                            { min: 6, message: 'Minimum 6 characters' },
                        ] : [
                            { required: true, message: 'Please enter password' },
                            { min: 6, message: 'Minimum 6 characters' },
                        ]}
                    >
                        <Input.Password prefix={<LockOutlined />} placeholder="••••••••" autoComplete="new-password" />
                    </Form.Item>

                    <Form.Item
                        name="password_confirmation"
                        label="Confirm Password"
                        dependencies={['password']}
                        rules={[
                            ({ getFieldValue }) => ({
                                validator(_, value) {
                                    const pw = getFieldValue('password');
                                    if (!pw && !value) return Promise.resolve();
                                    if (pw && !value) return Promise.reject(new Error('Please confirm password'));
                                    if (pw === value) return Promise.resolve();
                                    return Promise.reject(new Error('Passwords do not match'));
                                },
                            }),
                        ]}
                    >
                        <Input.Password prefix={<LockOutlined />} placeholder="••••••••" autoComplete="new-password" />
                    </Form.Item>

                    <Form.Item style={{ textAlign: 'right', marginBottom: 0 }}>
                        <Button onClick={closeModal} style={{ marginRight: 8 }}>Cancel</Button>
                        <Button type="primary" htmlType="submit" loading={isCreating || isUpdating}>
                            {isEditing ? 'Update User' : 'Create User'}
                        </Button>
                    </Form.Item>
                </Form>
            </Modal>
        </Card>
    );
}
