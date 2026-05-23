import React, { useState, useEffect } from 'react';
import {
    Table, Card, Button, Spin, Alert, Modal, Form, Input,
    Select, message, Space, Popconfirm, Tag, Row, Col, Drawer,
    Descriptions, Empty, Typography,
} from 'antd';
import {
    PlusOutlined, EditOutlined, DeleteOutlined, SearchOutlined,
    EyeOutlined, ReadOutlined, PhoneOutlined, EnvironmentOutlined,
} from '@ant-design/icons';
import {
    useGetStudentsQuery,
    useCreateStudentMutation,
    useUpdateStudentMutation,
    useDeleteStudentMutation,
} from '../store/apiSlice.js';
import { usePermissions } from '../utils/permissions.js';

const { Text } = Typography;

const FUNDING_OPTIONS = [
    { value: 'unfunded', label: 'Unfunded' },
    { value: 'partially_funded', label: 'Partially Funded' },
    { value: 'fully_funded', label: 'Fully Funded' },
];

const fundingColor = (status) => {
    switch (status) {
        case 'fully_funded':     return 'green';
        case 'partially_funded': return 'gold';
        case 'unfunded':         return 'red';
        default:                 return 'default';
    }
};

const formatBdt = (n) => `${Number(n || 0).toLocaleString()} BDT`;

export default function StudentsPage() {
    const { can } = usePermissions();
    const [filters, setFilters] = useState({ search: '', funding_status: '' });
    const { data, error, isLoading, isFetching } = useGetStudentsQuery(filters);
    const [createStudent, { isLoading: isCreating }] = useCreateStudentMutation();
    const [updateStudent, { isLoading: isUpdating }] = useUpdateStudentMutation();
    const [deleteStudent] = useDeleteStudentMutation();

    const [isModalOpen, setIsModalOpen] = useState(false);
    const [editing, setEditing] = useState(null);
    const [drawerOpen, setDrawerOpen] = useState(false);
    const [drawerStudent, setDrawerStudent] = useState(null);
    const [form] = Form.useForm();
    const isEditing = Boolean(editing);

    useEffect(() => {
        if (!isModalOpen) return;
        if (editing) {
            form.setFieldsValue({
                student_name:      editing.student_name,
                guardian_name:     editing.guardian_name,
                guardian_phone:    editing.guardian_phone,
                address:           editing.address,
                post_code:         editing.post_code,
                educational_level: editing.educational_level,
                institution_name:  editing.institution_name,
                funding_status:    editing.funding_status,
            });
        } else {
            form.resetFields();
            form.setFieldsValue({ funding_status: 'unfunded' });
        }
    }, [isModalOpen, editing, form]);

    const openCreate = () => { setEditing(null); setIsModalOpen(true); };
    const openEdit = (row) => { setEditing(row); setIsModalOpen(true); };
    const closeModal = () => { setIsModalOpen(false); setEditing(null); form.resetFields(); };

    const openDrawer = (student) => { setDrawerStudent(student); setDrawerOpen(true); };
    const closeDrawer = () => { setDrawerOpen(false); setDrawerStudent(null); };

    const handleFinish = async (values) => {
        try {
            if (isEditing) {
                await updateStudent({ id: editing.id, ...values }).unwrap();
                message.success('Student updated successfully!');
            } else {
                await createStudent(values).unwrap();
                message.success('Student created successfully!');
            }
            closeModal();
        } catch (err) {
            const errors = err?.data?.errors;
            if (errors) {
                const firstField = Object.keys(errors)[0];
                message.error(errors[firstField][0]);
            } else {
                message.error(err?.data?.message || `Failed to ${isEditing ? 'update' : 'create'} student`);
            }
        }
    };

    const handleDelete = async (id) => {
        try {
            await deleteStudent(id).unwrap();
            message.success('Student deleted');
        } catch (err) {
            message.error(err?.data?.message || 'Failed to delete student');
        }
    };

    const baseColumns = [
        {
            title: 'Student ID',
            dataIndex: 'student_id',
            key: 'student_id',
            render: (code) => <Tag color="purple">{code}</Tag>,
        },
        { title: 'Name', dataIndex: 'student_name', key: 'student_name' },
        {
            title: 'Guardian',
            key: 'guardian',
            render: (_, r) => (
                <div style={{ lineHeight: 1.4 }}>
                    <div>{r.guardian_name || <Text type="secondary">—</Text>}</div>
                    {r.guardian_phone && (
                        <div style={{ fontSize: 12, color: '#888' }}>
                            <PhoneOutlined /> {r.guardian_phone}
                        </div>
                    )}
                </div>
            ),
        },
        {
            title: 'Education',
            key: 'education',
            render: (_, r) => r.educational_level || r.institution_name
                ? (
                    <div style={{ lineHeight: 1.4 }}>
                        {r.educational_level && <div>{r.educational_level}</div>}
                        {r.institution_name && (
                            <div style={{ fontSize: 12, color: '#888' }}>{r.institution_name}</div>
                        )}
                    </div>
                )
                : <Text type="secondary">—</Text>,
        },
        { title: 'Post Code', dataIndex: 'post_code', key: 'post_code' },
        {
            title: 'Funding',
            dataIndex: 'funding_status',
            key: 'funding_status',
            render: (s) => (
                <Tag color={fundingColor(s)}>
                    {(s || 'unfunded').replace('_', ' ')}
                </Tag>
            ),
        },
        {
            title: 'Donations',
            dataIndex: 'donations_count',
            key: 'donations_count',
            width: 100,
            render: (v) => <Tag color="blue">{v ?? 0}</Tag>,
        },
    ];

    const actionColumn = [{
        title: 'Actions',
        key: 'actions',
        width: 220,
        render: (_, record) => (
            <Space>
                <Button size="small" icon={<EyeOutlined />} onClick={() => openDrawer(record)}>View</Button>
                {can('students.edit') && (
                    <Button size="small" icon={<EditOutlined />} onClick={() => openEdit(record)}>Edit</Button>
                )}
                {can('students.delete') && (
                    <Popconfirm
                        title="Delete this student?"
                        description="Students with donation history cannot be deleted."
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
    }];

    const columns = [...baseColumns, ...actionColumn];

    if (isLoading) return <div style={{ textAlign: 'center', marginTop: 50 }}><Spin size="large" /></div>;
    if (error) return <Alert message="Error" description="Failed to load students" type="error" showIcon />;

    return (
        <Card
            title="Students"
            extra={can('students.create') && (
                <Button type="primary" icon={<PlusOutlined />} onClick={openCreate}>Add Student</Button>
            )}
        >
            <Row gutter={[12, 12]} style={{ marginBottom: 16 }}>
                <Col xs={24} sm={12} md={10}>
                    <Input
                        prefix={<SearchOutlined />}
                        placeholder="Search by name, student ID, or post code"
                        allowClear
                        value={filters.search}
                        onChange={(e) => setFilters((f) => ({ ...f, search: e.target.value }))}
                    />
                </Col>
                <Col xs={18} sm={8} md={6}>
                    <Select
                        placeholder="Funding status"
                        allowClear
                        style={{ width: '100%' }}
                        value={filters.funding_status || undefined}
                        onChange={(v) => setFilters((f) => ({ ...f, funding_status: v || '' }))}
                        options={FUNDING_OPTIONS}
                    />
                </Col>
                <Col>
                    <Button onClick={() => setFilters({ search: '', funding_status: '' })}>Reset</Button>
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
                title={isEditing ? `Edit Student: ${editing?.student_id}` : 'Create New Student'}
                open={isModalOpen}
                onCancel={closeModal}
                footer={null}
                destroyOnClose
                width={560}
                centered
            >
                {!isEditing && (
                    <Alert
                        type="info"
                        showIcon
                        message="Student ID will be auto-generated (STD-2001, STD-2002, …)"
                        style={{ marginBottom: 16 }}
                    />
                )}
                <Form form={form} layout="vertical" onFinish={handleFinish} initialValues={{ funding_status: 'unfunded' }}>
                    <Form.Item
                        name="student_name"
                        label="Student Name"
                        rules={[{ required: true, message: 'Please enter name' }]}
                    >
                        <Input prefix={<ReadOutlined />} placeholder="Full name" />
                    </Form.Item>

                    <Row gutter={12}>
                        <Col xs={24} sm={12}>
                            <Form.Item name="guardian_name" label="Guardian Name">
                                <Input placeholder="Father / Mother / Guardian" />
                            </Form.Item>
                        </Col>
                        <Col xs={24} sm={12}>
                            <Form.Item name="guardian_phone" label="Guardian Phone">
                                <Input prefix={<PhoneOutlined />} placeholder="+447XXXXXXXXX" />
                            </Form.Item>
                        </Col>
                    </Row>

                    <Form.Item name="address" label="Address">
                        <Input.TextArea rows={2} />
                    </Form.Item>

                    <Form.Item name="post_code" label="Post Code">
                        <Input prefix={<EnvironmentOutlined />} placeholder="E1 1DU" />
                    </Form.Item>

                    <Row gutter={12}>
                        <Col xs={24} sm={12}>
                            <Form.Item name="educational_level" label="Educational Level">
                                <Select
                                    placeholder="Select level"
                                    allowClear
                                    options={[
                                        { value: 'Primary',   label: 'Primary' },
                                        { value: 'Secondary', label: 'Secondary' },
                                        { value: 'Higher',    label: 'Higher' },
                                    ]}
                                />
                            </Form.Item>
                        </Col>
                        <Col xs={24} sm={12}>
                            <Form.Item name="institution_name" label="Institution">
                                <Input placeholder="School / college / university" />
                            </Form.Item>
                        </Col>
                    </Row>

                    <Form.Item name="funding_status" label="Funding Status">
                        <Select options={FUNDING_OPTIONS} />
                    </Form.Item>

                    <Form.Item style={{ textAlign: 'right', marginBottom: 0 }}>
                        <Button onClick={closeModal} style={{ marginRight: 8 }}>Cancel</Button>
                        <Button type="primary" htmlType="submit" loading={isCreating || isUpdating}>
                            {isEditing ? 'Update Student' : 'Save Change'}
                        </Button>
                    </Form.Item>
                </Form>
            </Modal>

            <Drawer
                title={drawerStudent ? `${drawerStudent.student_name} (${drawerStudent.student_id})` : 'Student details'}
                open={drawerOpen}
                onClose={closeDrawer}
                width={Math.min(640, window.innerWidth - 40)}
                destroyOnClose
            >
                {drawerStudent && (
                    <Descriptions column={1} size="small" bordered>
                        <Descriptions.Item label="Student ID">{drawerStudent.student_id}</Descriptions.Item>
                        <Descriptions.Item label="Name">{drawerStudent.student_name}</Descriptions.Item>
                        <Descriptions.Item label="Guardian">
                            {drawerStudent.guardian_name || '—'}
                            {drawerStudent.guardian_phone && ` · ${drawerStudent.guardian_phone}`}
                        </Descriptions.Item>
                        <Descriptions.Item label="Address">{drawerStudent.address || '—'}</Descriptions.Item>
                        <Descriptions.Item label="Post Code">{drawerStudent.post_code || '—'}</Descriptions.Item>
                        <Descriptions.Item label="Education">
                            {drawerStudent.educational_level || '—'}
                            {drawerStudent.institution_name && ` · ${drawerStudent.institution_name}`}
                        </Descriptions.Item>
                        <Descriptions.Item label="Funding">
                            <Tag color={fundingColor(drawerStudent.funding_status)}>
                                {(drawerStudent.funding_status || 'unfunded').replace('_', ' ')}
                            </Tag>
                        </Descriptions.Item>
                        <Descriptions.Item label="Donations Received">
                            <Text strong>{drawerStudent.donations_count ?? 0}</Text>
                        </Descriptions.Item>
                    </Descriptions>
                )}
                {!drawerStudent && <Empty />}
            </Drawer>
        </Card>
    );
}
