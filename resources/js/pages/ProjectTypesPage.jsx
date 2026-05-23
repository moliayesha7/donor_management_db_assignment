import React, { useState, useEffect } from 'react';
import {
    useGetProjectTypesQuery,
    useCreateProjectTypeMutation,
    useUpdateProjectTypeMutation,
    useDeleteProjectTypeMutation,
} from '../store/apiSlice.js';
import {
    Table, Card, Button, Spin, Alert, Modal, Form, Input,
    Select, message, Space, Popconfirm, Tag, Row, Col,
} from 'antd';
import { PlusOutlined, EditOutlined, DeleteOutlined, SearchOutlined } from '@ant-design/icons';
import { usePermissions } from '../utils/permissions.js';

export default function ProjectTypesPage() {
    const { can } = usePermissions();
    const [filters, setFilters] = useState({ search: '', status: '' });
    const { data, error, isLoading, isFetching } = useGetProjectTypesQuery(filters);
    const [createType, { isLoading: isCreating }] = useCreateProjectTypeMutation();
    const [updateType, { isLoading: isUpdating }] = useUpdateProjectTypeMutation();
    const [deleteType] = useDeleteProjectTypeMutation();

    const [isModalOpen, setIsModalOpen] = useState(false);
    const [editing, setEditing] = useState(null);
    const [form] = Form.useForm();
    const isEditing = Boolean(editing);

    useEffect(() => {
        if (isModalOpen && editing) {
            form.setFieldsValue({ name: editing.name, status: editing.status });
        } else if (isModalOpen) {
            form.resetFields();
            form.setFieldsValue({ status: 'active' });
        }
    }, [isModalOpen, editing, form]);

    const openCreate = () => { setEditing(null); setIsModalOpen(true); };
    const openEdit = (row) => { setEditing(row); setIsModalOpen(true); };
    const closeModal = () => { setIsModalOpen(false); setEditing(null); form.resetFields(); };

    const handleFinish = async (values) => {
        try {
            if (isEditing) {
                await updateType({ id: editing.id, ...values }).unwrap();
                message.success('Project type updated!');
            } else {
                await createType(values).unwrap();
                message.success('Project type created!');
            }
            closeModal();
        } catch (err) {
            message.error(err?.data?.message || `Failed to ${isEditing ? 'update' : 'create'} project type`);
        }
    };

    const handleDelete = async (id) => {
        try {
            await deleteType(id).unwrap();
            message.success('Project type deleted');
        } catch (err) {
            message.error(err?.data?.message || 'Failed to delete project type');
        }
    };

    const baseColumns = [
        { title: 'ID', dataIndex: 'id', key: 'id', width: 60 },
        { title: 'Name', dataIndex: 'name', key: 'name' },
        {
            title: 'Status',
            dataIndex: 'status',
            key: 'status',
            render: (s) => <Tag color={s === 'active' ? 'green' : 'red'}>{s}</Tag>,
        },
    ];

    const actionColumn = (can('project-types.edit') || can('project-types.delete')) ? [{
        title: 'Actions',
        key: 'actions',
        width: 180,
        render: (_, record) => (
            <Space>
                {can('project-types.edit') && (
                    <Button size="small" icon={<EditOutlined />} onClick={() => openEdit(record)}>Edit</Button>
                )}
                {can('project-types.delete') && (
                    <Popconfirm
                        title="Delete this project type?"
                        description="Types linked to projects cannot be deleted."
                        okText="Delete"
                        okButtonProps={{ danger: true }}
                        cancelText="Cancel"
                        onConfirm={() => handleDelete(record.id)}
                    >
                        <Button size="small" danger icon={<DeleteOutlined />}>Delete</Button>
                    </Popconfirm>
                )}
            </Space>
        ),
    }] : [];

    const columns = [...baseColumns, ...actionColumn];

    if (isLoading) return <div style={{ textAlign: 'center', marginTop: 50 }}><Spin size="large" /></div>;
    if (error) return <Alert message="Error" description="Failed to load project types" type="error" showIcon />;

    return (
        <Card
            title="Project Types"
            extra={can('project-types.create') && (
                <Button type="primary" icon={<PlusOutlined />} onClick={openCreate}>Add Type</Button>
            )}
        >
            <Row gutter={[12, 12]} style={{ marginBottom: 16 }}>
                <Col xs={24} sm={12} md={8}>
                    <Input
                        prefix={<SearchOutlined />}
                        placeholder="Search by name"
                        allowClear
                        value={filters.search}
                        onChange={(e) => setFilters((f) => ({ ...f, search: e.target.value }))}
                    />
                </Col>
                <Col xs={12} sm={8} md={5}>
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
                    <Button onClick={() => setFilters({ search: '', status: '' })}>Reset</Button>
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
                title={isEditing ? 'Edit Project Type' : 'Create Project Type'}
                open={isModalOpen}
                onCancel={closeModal}
                footer={null}
                destroyOnClose
            >
                <Form form={form} layout="vertical" onFinish={handleFinish} style={{ marginTop: 20 }}>
                    <Form.Item
                        name="name"
                        label="Name"
                        rules={[{ required: true, message: 'Please enter name' }]}
                    >
                        <Input placeholder="e.g., Zakat" />
                    </Form.Item>

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

                    <Form.Item style={{ textAlign: 'right', marginBottom: 0 }}>
                        <Button onClick={closeModal} style={{ marginRight: 8 }}>Cancel</Button>
                        <Button type="primary" htmlType="submit" loading={isCreating || isUpdating}>
                            {isEditing ? 'Update' : 'Save'}
                        </Button>
                    </Form.Item>
                </Form>
            </Modal>
        </Card>
    );
}
