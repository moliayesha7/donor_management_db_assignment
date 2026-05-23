import React, { useState, useEffect, useMemo } from 'react';
import {
    useGetProjectsQuery,
    useCreateProjectMutation,
    useUpdateProjectMutation,
    useDeleteProjectMutation,
    useGetProjectTypesQuery,
} from '../store/apiSlice.js';
import {
    Table, Card, Button, Spin, Alert, Modal, Form, Input, InputNumber,
    Select, message, Space, Popconfirm, Tag, Row, Col,
} from 'antd';
import { PlusOutlined, EditOutlined, DeleteOutlined, SearchOutlined } from '@ant-design/icons';
import { usePermissions } from '../utils/permissions.js';

const STATUS_COLORS = {
    pending: 'gold',
    active: 'green',
    completed: 'blue',
    suspended: 'red',
};

export default function ProjectsPage() {
    const { can } = usePermissions();
    const [filters, setFilters] = useState({ search: '', status: '', project_type_id: '' });
    const queryArgs = useMemo(() => filters, [filters]);

    const { data: projectTypesData, isLoading: isTypesLoading } = useGetProjectTypesQuery({ status: 'active' });
    const { data, error, isLoading, isFetching } = useGetProjectsQuery(queryArgs);
    const [createProject, { isLoading: isCreating }] = useCreateProjectMutation();
    const [updateProject, { isLoading: isUpdating }] = useUpdateProjectMutation();
    const [deleteProject] = useDeleteProjectMutation();

    const [isModalOpen, setIsModalOpen] = useState(false);
    const [editingProject, setEditingProject] = useState(null);
    const [form] = Form.useForm();

    const isEditing = Boolean(editingProject);

    useEffect(() => {
        if (isModalOpen && editingProject) {
            form.setFieldsValue({
                project_type_id: editingProject.project_type_id,
                name: editingProject.name,
                project_code: editingProject.project_code,
                budget: Number(editingProject.budget),
                description: editingProject.description,
                status: editingProject.status,
            });
        } else if (isModalOpen) {
            form.resetFields();
        }
    }, [isModalOpen, editingProject, form]);

    const openCreate = () => { setEditingProject(null); setIsModalOpen(true); };
    const openEdit = (project) => { setEditingProject(project); setIsModalOpen(true); };
    const closeModal = () => { setIsModalOpen(false); setEditingProject(null); form.resetFields(); };

    const handleFinish = async (values) => {
        try {
            if (isEditing) {
                await updateProject({ id: editingProject.id, ...values }).unwrap();
                message.success('Project updated successfully!');
            } else {
                await createProject(values).unwrap();
                message.success('Project created successfully!');
            }
            closeModal();
        } catch (err) {
            message.error(err?.data?.message || `Failed to ${isEditing ? 'update' : 'create'} project`);
        }
    };

    const handleDelete = async (id) => {
        try {
            await deleteProject(id).unwrap();
            message.success('Project deleted');
        } catch (err) {
            message.error(err?.data?.message || 'Failed to delete project');
        }
    };

    const resetFilters = () => setFilters({ search: '', status: '', project_type_id: '' });

    const baseColumns = [
        { title: 'ID', dataIndex: 'id', key: 'id', width: 60 },
        { title: 'Project Code', dataIndex: 'project_code', key: 'project_code' },
        { title: 'Name', dataIndex: 'name', key: 'name' },
        { title: 'Type', key: 'type', render: (_, r) => r?.type?.name || '-' },
        {
            title: 'Budget',
            dataIndex: 'budget',
            key: 'budget',
            render: (amount) => `${Number(amount).toLocaleString()} BDT`,
        },
        {
            title: 'Status',
            dataIndex: 'status',
            key: 'status',
            render: (s) => <Tag color={STATUS_COLORS[s] || 'default'}>{s}</Tag>,
        },
    ];

    const actionColumn = (can('projects.edit') || can('projects.delete')) ? [{
        title: 'Actions',
        key: 'actions',
        width: 180,
        render: (_, record) => (
            <Space>
                {can('projects.edit') && (
                    <Button size="small" icon={<EditOutlined />} onClick={() => openEdit(record)}>Edit</Button>
                )}
                {can('projects.delete') && (
                    <Popconfirm
                        title="Delete this project?"
                        description="This cannot be undone."
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
    if (error) return <Alert message="Error" description="Failed to load projects" type="error" showIcon />;

    return (
        <Card
            title="Projects"
            extra={can('projects.create') && (
                <Button type="primary" icon={<PlusOutlined />} onClick={openCreate}>Add Project</Button>
            )}
        >
            <Row gutter={[12, 12]} style={{ marginBottom: 16 }}>
                <Col xs={24} sm={10} md={8}>
                    <Input
                        prefix={<SearchOutlined />}
                        placeholder="Search by name or code"
                        allowClear
                        value={filters.search}
                        onChange={(e) => setFilters((f) => ({ ...f, search: e.target.value }))}
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
                            { value: 'pending', label: 'Pending' },
                            { value: 'active', label: 'Active' },
                            { value: 'completed', label: 'Completed' },
                            { value: 'suspended', label: 'Suspended' },
                        ]}
                    />
                </Col>
                <Col xs={12} sm={7} md={5}>
                    <Select
                        placeholder="Project Type"
                        allowClear
                        style={{ width: '100%' }}
                        loading={isTypesLoading}
                        value={filters.project_type_id || undefined}
                        onChange={(v) => setFilters((f) => ({ ...f, project_type_id: v || '' }))}
                        options={(projectTypesData?.data || []).map((t) => ({ value: t.id, label: t.name }))}
                    />
                </Col>
                <Col>
                    <Button onClick={resetFilters}>Reset</Button>
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
                title={isEditing ? 'Edit Project' : 'Create New Project'}
                open={isModalOpen}
                onCancel={closeModal}
                footer={null}
                destroyOnClose
            >
                <Form form={form} layout="vertical" onFinish={handleFinish} style={{ marginTop: 20 }}>
                    <Form.Item
                        name="project_type_id"
                        label="Project Type"
                        rules={[{ required: true, message: 'Please select project type' }]}
                    >
                        <Select placeholder="Select Type" loading={isTypesLoading}>
                            {projectTypesData?.data?.map((type) => (
                                <Select.Option key={type.id} value={type.id}>{type.name}</Select.Option>
                            ))}
                        </Select>
                    </Form.Item>

                    <Form.Item
                        name="name"
                        label="Project Name"
                        rules={[{ required: true, message: 'Please enter project name' }]}
                    >
                        <Input placeholder="e.g., Winter Clothes Distribution" />
                    </Form.Item>

                    <Form.Item
                        name="project_code"
                        label="Project Code"
                        rules={[{ required: true, message: 'Please enter unique project code' }]}
                    >
                        <Input placeholder="e.g., PRJ-WINTER-2026" />
                    </Form.Item>

                    <Form.Item
                        name="budget"
                        label="Budget (BDT)"
                        rules={[{ required: true, message: 'Please enter budget' }]}
                    >
                        <InputNumber min={0} style={{ width: '100%' }} placeholder="Amount" />
                    </Form.Item>

                    {isEditing && (
                        <Form.Item
                            name="status"
                            label="Status"
                            rules={[{ required: true, message: 'Please select status' }]}
                        >
                            <Select>
                                <Select.Option value="pending">Pending</Select.Option>
                                <Select.Option value="active">Active</Select.Option>
                                <Select.Option value="completed">Completed</Select.Option>
                                <Select.Option value="suspended">Suspended</Select.Option>
                            </Select>
                        </Form.Item>
                    )}

                    <Form.Item name="description" label="Description">
                        <Input.TextArea rows={3} placeholder="Project details..." />
                    </Form.Item>

                    <Form.Item style={{ textAlign: 'right', marginBottom: 0 }}>
                        <Button onClick={closeModal} style={{ marginRight: 8 }}>Cancel</Button>
                        <Button type="primary" htmlType="submit" loading={isCreating || isUpdating}>
                            {isEditing ? 'Update Project' : 'Save Project'}
                        </Button>
                    </Form.Item>
                </Form>
            </Modal>
        </Card>
    );
}
