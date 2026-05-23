import React, { useState, useEffect } from 'react';
import {
    Table, Card, Button, Modal, Form, Input, Radio, message, Space, Popconfirm,
    Tag, Row, Col, Alert, Spin,
} from 'antd';
import { PlusOutlined, EditOutlined, DeleteOutlined, SearchOutlined } from '@ant-design/icons';
import {
    useGetDonorSourcesQuery,
    useCreateDonorSourceMutation,
    useUpdateDonorSourceMutation,
    useDeleteDonorSourceMutation,
} from '../store/apiSlice.js';
import { usePermissions } from '../utils/permissions.js';

export default function DonorSourcesPage() {
    const { can } = usePermissions();
    const [filters, setFilters] = useState({ search: '', is_active: '' });
    const { data, error, isLoading, isFetching } = useGetDonorSourcesQuery(filters);
    const [createSource, { isLoading: isCreating }] = useCreateDonorSourceMutation();
    const [updateSource, { isLoading: isUpdating }] = useUpdateDonorSourceMutation();
    const [deleteSource] = useDeleteDonorSourceMutation();

    const [isModalOpen, setIsModalOpen] = useState(false);
    const [editing, setEditing] = useState(null);
    const [form] = Form.useForm();
    const isEditing = Boolean(editing);

    useEffect(() => {
        if (!isModalOpen) return;
        if (editing) {
            form.setFieldsValue({
                name: editing.name,
                description: editing.description,
                is_active: editing.is_active ? 'Yes' : 'No',
            });
        } else {
            form.resetFields();
            form.setFieldsValue({ is_active: 'No' });
        }
    }, [isModalOpen, editing, form]);

    const openCreate = () => { setEditing(null); setIsModalOpen(true); };
    const openEdit = (row) => { setEditing(row); setIsModalOpen(true); };
    const closeModal = () => { setIsModalOpen(false); setEditing(null); form.resetFields(); };

    const handleFinish = async (values) => {
        const payload = {
            name: values.name,
            description: values.description || null,
            is_active: values.is_active === 'Yes',
        };
        try {
            if (isEditing) {
                await updateSource({ id: editing.id, ...payload }).unwrap();
                message.success('Source updated successfully');
            } else {
                await createSource(payload).unwrap();
                message.success('Source created successfully');
            }
            closeModal();
        } catch (err) {
            const errs = err?.data?.errors;
            if (errs) {
                const f = Object.keys(errs)[0];
                message.error(errs[f][0]);
            } else {
                message.error(err?.data?.message || 'Operation failed');
            }
        }
    };

    const handleDelete = async (id) => {
        try {
            await deleteSource(id).unwrap();
            message.success('Source deleted');
        } catch (err) {
            message.error(err?.data?.message || 'Failed to delete');
        }
    };

    const columns = [
        { title: 'Name', dataIndex: 'name', key: 'name' },
        { title: 'Description', dataIndex: 'description', key: 'description', render: (v) => v || '—' },
        {
            title: 'Donors',
            dataIndex: 'donors_count',
            key: 'donors_count',
            width: 100,
            render: (v) => <Tag color="blue">{v ?? 0}</Tag>,
        },
        {
            title: 'Enabled',
            dataIndex: 'is_active',
            key: 'is_active',
            width: 100,
            render: (v) => v
                ? <Tag color="green">Yes</Tag>
                : <Tag color="red">No</Tag>,
        },
        {
            title: 'Actions',
            key: 'actions',
            width: 200,
            render: (_, record) => (
                <Space>
                    {can('donor-sources.edit') && (
                        <Button size="small" icon={<EditOutlined />} onClick={() => openEdit(record)}>Edit</Button>
                    )}
                    {can('donor-sources.delete') && (
                        <Popconfirm
                            title="Delete this source?"
                            description="Sources linked to donors cannot be deleted."
                            okText="Delete"
                            okButtonProps={{ danger: true }}
                            onConfirm={() => handleDelete(record.id)}
                        >
                            <Button size="small" danger icon={<DeleteOutlined />}>Delete</Button>
                        </Popconfirm>
                    )}
                </Space>
            ),
        },
    ];

    if (isLoading) return <div style={{ textAlign: 'center', marginTop: 50 }}><Spin size="large" /></div>;
    if (error) return <Alert message="Error" description="Failed to load donor sources" type="error" showIcon />;

    return (
        <Card
            title="All Sources"
            extra={can('donor-sources.create') && (
                <Button type="primary" icon={<PlusOutlined />} onClick={openCreate}>Add New Source</Button>
            )}
        >
            <Row gutter={[12, 12]} style={{ marginBottom: 16 }}>
                <Col xs={24} sm={12} md={10}>
                    <Input
                        prefix={<SearchOutlined />}
                        placeholder="Search by name or description"
                        allowClear
                        value={filters.search}
                        onChange={(e) => setFilters((f) => ({ ...f, search: e.target.value }))}
                    />
                </Col>
                <Col>
                    <Radio.Group
                        value={filters.is_active}
                        onChange={(e) => setFilters((f) => ({ ...f, is_active: e.target.value }))}
                        optionType="button"
                    >
                        <Radio.Button value="">All</Radio.Button>
                        <Radio.Button value="true">Active</Radio.Button>
                        <Radio.Button value="false">Inactive</Radio.Button>
                    </Radio.Group>
                </Col>
            </Row>

            <Table
                dataSource={data?.data || []}
                columns={columns}
                rowKey="id"
                loading={isFetching}
            />

            <Modal
                title={isEditing ? `Edit Source: ${editing?.name}` : 'Create Donor Source'}
                open={isModalOpen}
                onCancel={closeModal}
                footer={null}
                destroyOnClose
                width={520}
            >
                <Form form={form} layout="vertical" onFinish={handleFinish}>
                    <Form.Item
                        name="name"
                        label="Name"
                        rules={[{ required: true, message: 'Please enter source name' }]}
                    >
                        <Input placeholder="e.g., Website, Facebook Campaign" />
                    </Form.Item>
                    <Form.Item name="description" label="Description">
                        <Input.TextArea rows={2} />
                    </Form.Item>
                    <Form.Item name="is_active" label="Active">
                        <Radio.Group optionType="button" buttonStyle="solid">
                            <Radio.Button value="No">No</Radio.Button>
                            <Radio.Button value="Yes">Yes</Radio.Button>
                        </Radio.Group>
                    </Form.Item>
                    <Form.Item style={{ textAlign: 'right', marginBottom: 0 }}>
                        <Button onClick={closeModal} style={{ marginRight: 8 }}>Cancel</Button>
                        <Button type="primary" htmlType="submit" loading={isCreating || isUpdating}>
                            {isEditing ? 'Update Source' : 'Save Change'}
                        </Button>
                    </Form.Item>
                </Form>
            </Modal>
        </Card>
    );
}
