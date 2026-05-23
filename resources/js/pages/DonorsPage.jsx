import React, { useState, useEffect } from 'react';
import {
    Table, Card, Button, Spin, Alert, Modal, Form, Input,
    Select, message, Space, Popconfirm, Tag, Row, Col, Drawer,
    Descriptions, Empty, Typography, Checkbox,
} from 'antd';
import {
    PlusOutlined, EditOutlined, DeleteOutlined, SearchOutlined,
    EyeOutlined, MailOutlined, PhoneOutlined, EnvironmentOutlined,
} from '@ant-design/icons';
import {
    useGetDonorsQuery,
    useGetProjectsQuery,
    useGetDonorSourcesQuery,
    useCreateDonorMutation,
    useUpdateDonorMutation,
    useDeleteDonorMutation,
    useLazyGetDonorQuery,
} from '../store/apiSlice.js';
import { usePermissions } from '../utils/permissions.js';

const { Text } = Typography;

const formatBdt = (n) => `${Number(n || 0).toLocaleString()} BDT`;

const COUNTRY_OPTIONS = [
    { value: 'United Kingdom', label: 'United Kingdom' },
    { value: 'Bangladesh', label: 'Bangladesh' },
    { value: 'United States', label: 'United States' },
    { value: 'India', label: 'India' },
    { value: 'Other', label: 'Other' },
];

export default function DonorsPage() {
    const { can } = usePermissions();
    const [filters, setFilters] = useState({ search: '', preferred_project_id: '' });
    const { data, error, isLoading, isFetching } = useGetDonorsQuery(filters);
    const { data: projectsData, isLoading: isProjectsLoading } = useGetProjectsQuery({ status: 'active' });
    const { data: sourcesData, isLoading: isSourcesLoading } = useGetDonorSourcesQuery({ is_active: true });
    const [createDonor, { isLoading: isCreating }] = useCreateDonorMutation();
    const [updateDonor, { isLoading: isUpdating }] = useUpdateDonorMutation();
    const [deleteDonor] = useDeleteDonorMutation();
    const [fetchDonor, { data: donorDetail, isFetching: isDetailFetching }] = useLazyGetDonorQuery();

    const [isModalOpen, setIsModalOpen] = useState(false);
    const [editing, setEditing] = useState(null);
    const [drawerOpen, setDrawerOpen] = useState(false);
    const [form] = Form.useForm();
    const isEditing = Boolean(editing);

    useEffect(() => {
        if (!isModalOpen) return;
        if (editing) {
            form.setFieldsValue({
                name: editing.name,
                address_lookup: editing.address_lookup,
                address_line_1: editing.address_line_1,
                address_line_2: editing.address_line_2,
                address_line_3: editing.address_line_3,
                city: editing.city,
                post_code: editing.post_code,
                phone_number: editing.phone_number,
                email: editing.email,
                country: editing.country || 'United Kingdom',
                donor_source_id: editing.donor_source_id,
                preferred_project_id: editing.preferred_project_id,
                notify_email: editing.notify_email ?? true,
                notify_sms: editing.notify_sms ?? true,
                notify_whatsapp: editing.notify_whatsapp ?? true,
            });
        } else {
            form.resetFields();
            form.setFieldsValue({
                country: 'United Kingdom',
                notify_email: true,
                notify_sms: true,
                notify_whatsapp: true,
            });
        }
    }, [isModalOpen, editing, form]);

    const openCreate = () => { setEditing(null); setIsModalOpen(true); };
    const openEdit = (row) => { setEditing(row); setIsModalOpen(true); };
    const closeModal = () => { setIsModalOpen(false); setEditing(null); form.resetFields(); };

    const openDrawer = (donor) => {
        setDrawerOpen(true);
        fetchDonor(donor.id);
    };
    const closeDrawer = () => setDrawerOpen(false);

    const handleFinish = async (values) => {
        try {
            if (isEditing) {
                await updateDonor({ id: editing.id, ...values }).unwrap();
                message.success('Donor updated successfully!');
            } else {
                await createDonor(values).unwrap();
                message.success('Donor created successfully!');
            }
            closeModal();
        } catch (err) {
            const errors = err?.data?.errors;
            if (errors) {
                const firstField = Object.keys(errors)[0];
                message.error(errors[firstField][0]);
            } else {
                message.error(err?.data?.message || `Failed to ${isEditing ? 'update' : 'create'} donor`);
            }
        }
    };

    const handleDelete = async (id) => {
        try {
            await deleteDonor(id).unwrap();
            message.success('Donor deleted');
        } catch (err) {
            message.error(err?.data?.message || 'Failed to delete donor');
        }
    };

    const baseColumns = [
        {
            title: 'Donor ID',
            dataIndex: 'donor_id_code',
            key: 'donor_id_code',
            render: (code) => <Tag color="purple">{code}</Tag>,
        },
        { title: 'Name', dataIndex: 'name', key: 'name' },
        {
            title: 'Contact',
            key: 'contact',
            render: (_, r) => (
                <div style={{ lineHeight: 1.4 }}>
                    <div><PhoneOutlined /> {r.phone_number}</div>
                    {r.email && <div style={{ fontSize: 12, color: '#888' }}><MailOutlined /> {r.email}</div>}
                </div>
            ),
        },
        {
            title: 'Address Line 1',
            key: 'address_line_1',
            render: (_, r) => r.address_line_1
                ? <Text>{r.address_line_1}{r.city ? `, ${r.city}` : ''}</Text>
                : <Text type="secondary">—</Text>,
        },
        {
            title: 'Post Code',
            dataIndex: 'post_code',
            key: 'post_code',
        },
        {
            title: 'Source',
            key: 'source',
            render: (_, r) => r?.donor_source?.name
                ? <Tag color="geekblue">{r.donor_source.name}</Tag>
                : <Text type="secondary">—</Text>,
        },
        {
            title: 'Preferred Project',
            key: 'preferred',
            render: (_, r) => r?.preferred_project?.name
                ? <Text>{r.preferred_project.name}</Text>
                : <Text type="secondary">—</Text>,
        },
        {
            title: 'Donations',
            key: 'donations',
            render: (_, r) => (
                <Space direction="vertical" size={0}>
                    <Text>{r.donations_count ?? 0} times</Text>
                    <Text type="secondary" style={{ fontSize: 12 }}>
                        {formatBdt(r.donations_total)}
                    </Text>
                </Space>
            ),
        },
        {
            title: 'Notify',
            key: 'notify',
            width: 150,
            render: (_, r) => (
                <Space size={4} wrap>
                    {r.notify_email     && <Tag color="blue">Email</Tag>}
                    {r.notify_sms       && <Tag color="orange">SMS</Tag>}
                    {r.notify_whatsapp  && <Tag color="green">WhatsApp</Tag>}
                    {!r.notify_email && !r.notify_sms && !r.notify_whatsapp && (
                        <Tag color="default">None</Tag>
                    )}
                </Space>
            ),
        },
    ];

    const actionColumn = [{
        title: 'Actions',
        key: 'actions',
        width: 220,
        render: (_, record) => (
            <Space>
                <Button size="small" icon={<EyeOutlined />} onClick={() => openDrawer(record)}>View</Button>
                {can('donors.edit') && (
                    <Button size="small" icon={<EditOutlined />} onClick={() => openEdit(record)}>Edit</Button>
                )}
                {can('donors.delete') && (
                    <Popconfirm
                        title="Delete this donor?"
                        description="Donors with donation history cannot be deleted."
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
    if (error) return <Alert message="Error" description="Failed to load donors" type="error" showIcon />;

    const donor = donorDetail?.data;

    return (
        <Card
            title="Donors"
            extra={can('donors.create') && (
                <Button type="primary" icon={<PlusOutlined />} onClick={openCreate}>Add Donor</Button>
            )}
        >
            <Row gutter={[12, 12]} style={{ marginBottom: 16 }}>
                <Col xs={24} sm={12} md={10}>
                    <Input
                        prefix={<SearchOutlined />}
                        placeholder="Search by name, donor ID, phone, or post code"
                        allowClear
                        value={filters.search}
                        onChange={(e) => setFilters((f) => ({ ...f, search: e.target.value }))}
                    />
                </Col>
                <Col xs={18} sm={8} md={6}>
                    <Select
                        placeholder="Preferred Project"
                        allowClear
                        style={{ width: '100%' }}
                        loading={isProjectsLoading}
                        value={filters.preferred_project_id || undefined}
                        onChange={(v) => setFilters((f) => ({ ...f, preferred_project_id: v || '' }))}
                        options={(projectsData?.data || []).map((p) => ({ value: p.id, label: p.name }))}
                    />
                </Col>
                <Col>
                    <Button onClick={() => setFilters({ search: '', preferred_project_id: '' })}>Reset</Button>
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
                title={isEditing ? `Edit Donor: ${editing?.donor_id_code}` : 'Create New Donor'}
                open={isModalOpen}
                onCancel={closeModal}
                footer={null}
                destroyOnClose
                width={760}
                centered
            >
                {!isEditing && (
                    <Alert
                        type="info"
                        showIcon
                        message="Donor ID will be auto-generated (DNR-1001, DNR-1002, …)"
                        style={{ marginBottom: 12 }}
                    />
                )}
                <Form
                    form={form}
                    layout="vertical"
                    onFinish={handleFinish}
                    initialValues={{ country: 'United Kingdom', notify_email: true, notify_sms: true, notify_whatsapp: true }}
                    size="middle"
                >
                    <Row gutter={12}>
                        <Col xs={24} sm={12}>
                            <Form.Item name="name" label="Name" rules={[{ required: true, message: 'Please enter name' }]}>
                                <Input placeholder="Donor full name" />
                            </Form.Item>
                        </Col>
                        <Col xs={24} sm={12}>
                            <Form.Item name="phone_number" label="Phone" rules={[{ required: true, message: 'Required' }]}>
                                <Input prefix={<PhoneOutlined />} placeholder="+4471XXXXXXXX" />
                            </Form.Item>
                        </Col>
                    </Row>

                    <Row gutter={12}>
                        <Col xs={24} sm={12}>
                            <Form.Item name="email" label="Email" rules={[{ type: 'email', message: 'Invalid email' }]}>
                                <Input prefix={<MailOutlined />} placeholder="optional@example.com" />
                            </Form.Item>
                        </Col>
                        <Col xs={24} sm={12}>
                            <Form.Item name="post_code" label="Post Code" rules={[{ required: true, message: 'Required' }]}>
                                <Input prefix={<EnvironmentOutlined />} placeholder="E1 1EW" />
                            </Form.Item>
                        </Col>
                    </Row>

                    <Row gutter={12}>
                        <Col xs={24} sm={12}>
                            <Form.Item name="address_line_1" label="Address Line 1" rules={[{ required: true, message: 'Required' }]}>
                                <Input />
                            </Form.Item>
                        </Col>
                        <Col xs={24} sm={12}>
                            <Form.Item name="address_line_2" label="Address Line 2">
                                <Input />
                            </Form.Item>
                        </Col>
                    </Row>

                    <Row gutter={12}>
                        <Col xs={24} sm={12}>
                            <Form.Item name="city" label="City" rules={[{ required: true, message: 'Required' }]}>
                                <Input />
                            </Form.Item>
                        </Col>
                        <Col xs={24} sm={12}>
                            <Form.Item name="country" label="Country">
                                <Select options={COUNTRY_OPTIONS} showSearch optionFilterProp="label" />
                            </Form.Item>
                        </Col>
                    </Row>

                    <Row gutter={12}>
                        <Col xs={24} sm={12}>
                            <Form.Item name="donor_source_id" label="Source">
                                <Select
                                    placeholder="Select source"
                                    allowClear
                                    loading={isSourcesLoading}
                                    options={(sourcesData?.data || []).map((s) => ({ value: s.id, label: s.name }))}
                                />
                            </Form.Item>
                        </Col>
                        <Col xs={24} sm={12}>
                            <Form.Item name="preferred_project_id" label="Preferred Project">
                                <Select
                                    placeholder="Optional"
                                    allowClear
                                    loading={isProjectsLoading}
                                    options={(projectsData?.data || []).map((p) => ({
                                        value: p.id,
                                        label: `${p.name} (${p.project_code})`,
                                    }))}
                                />
                            </Form.Item>
                        </Col>
                    </Row>

                    <Row gutter={12}>
                        <Col xs={24} sm={24}>
                            <Form.Item name="address_line_3" label="Address Line 3">
                                <Input />
                            </Form.Item>
                        </Col>
                    </Row>

                    <Form.Item label="Notification channels" style={{ marginBottom: 8 }}>
                        <Space size="large">
                            <Form.Item name="notify_email" valuePropName="checked" noStyle>
                                <Checkbox><MailOutlined /> Email</Checkbox>
                            </Form.Item>
                            <Form.Item name="notify_sms" valuePropName="checked" noStyle>
                                <Checkbox><PhoneOutlined /> SMS</Checkbox>
                            </Form.Item>
                            <Form.Item name="notify_whatsapp" valuePropName="checked" noStyle>
                                <Checkbox>WhatsApp</Checkbox>
                            </Form.Item>
                        </Space>
                    </Form.Item>

                    <Form.Item style={{ textAlign: 'right', marginBottom: 0, marginTop: 12 }}>
                        <Button onClick={closeModal} style={{ marginRight: 8 }}>Cancel</Button>
                        <Button type="primary" htmlType="submit" loading={isCreating || isUpdating}>
                            {isEditing ? 'Update Donor' : 'Save Change'}
                        </Button>
                    </Form.Item>
                </Form>
            </Modal>

            <Drawer
                title={donor ? `${donor.name} (${donor.donor_id_code})` : 'Donor details'}
                open={drawerOpen}
                onClose={closeDrawer}
                width={Math.min(640, window.innerWidth - 40)}
                destroyOnClose
            >
                {isDetailFetching && <div style={{ textAlign: 'center', marginTop: 50 }}><Spin /></div>}
                {donor && !isDetailFetching && (
                    <>
                        <Descriptions column={1} size="small" bordered style={{ marginBottom: 24 }}>
                            <Descriptions.Item label="Donor ID">{donor.donor_id_code}</Descriptions.Item>
                            <Descriptions.Item label="Phone">{donor.phone_number || '—'}</Descriptions.Item>
                            <Descriptions.Item label="Email">{donor.email || '—'}</Descriptions.Item>
                            <Descriptions.Item label="Address">
                                {[donor.address_line_1, donor.address_line_2, donor.address_line_3]
                                    .filter(Boolean).join(', ') || '—'}
                            </Descriptions.Item>
                            <Descriptions.Item label="City / County">
                                {[donor.city, donor.county].filter(Boolean).join(', ') || '—'}
                            </Descriptions.Item>
                            <Descriptions.Item label="Post Code">{donor.post_code}</Descriptions.Item>
                            <Descriptions.Item label="Country">{donor.country || '—'}</Descriptions.Item>
                            <Descriptions.Item label="Source">{donor.donor_source?.name || '—'}</Descriptions.Item>
                            <Descriptions.Item label="Preferred Project">
                                {donor.preferred_project
                                    ? `${donor.preferred_project.name} (${donor.preferred_project.project_code})`
                                    : '—'}
                            </Descriptions.Item>
                            <Descriptions.Item label="Notifications">
                                <Space size={4} wrap>
                                    {donor.notify_email    && <Tag color="blue">Email</Tag>}
                                    {donor.notify_sms      && <Tag color="orange">SMS</Tag>}
                                    {donor.notify_whatsapp && <Tag color="green">WhatsApp</Tag>}
                                    {!donor.notify_email && !donor.notify_sms && !donor.notify_whatsapp && (
                                        <Tag color="default">None</Tag>
                                    )}
                                </Space>
                            </Descriptions.Item>
                            <Descriptions.Item label="Added by">{donor.creator?.name || '—'}</Descriptions.Item>
                            <Descriptions.Item label="Total Donations">
                                <Text strong>{donor.donations_count ?? 0}</Text> ·{' '}
                                <Text type="success">{formatBdt(donor.donations_total)}</Text>
                            </Descriptions.Item>
                        </Descriptions>

                        <Typography.Title level={5}>Donation History</Typography.Title>
                        {donor.donations?.length ? (
                            <Table
                                size="small"
                                dataSource={donor.donations}
                                rowKey="id"
                                pagination={false}
                                columns={[
                                    {
                                        title: 'Date',
                                        dataIndex: 'transaction_date',
                                        key: 'transaction_date',
                                        render: (d) => d ? new Date(d).toLocaleDateString() : '—',
                                    },
                                    {
                                        title: 'Project',
                                        key: 'project',
                                        render: (_, r) => r.project?.name || '—',
                                    },
                                    {
                                        title: 'Student',
                                        key: 'student',
                                        render: (_, r) => r.student?.student_name || '—',
                                    },
                                    {
                                        title: 'Amount',
                                        dataIndex: 'amount',
                                        key: 'amount',
                                        render: (a) => <Text strong>{formatBdt(a)}</Text>,
                                    },
                                    {
                                        title: 'Status',
                                        dataIndex: 'status',
                                        key: 'status',
                                        render: (s) => (
                                            <Tag color={s === 'confirmed' ? 'green' : s === 'failed' ? 'red' : 'gold'}>
                                                {s}
                                            </Tag>
                                        ),
                                    },
                                ]}
                            />
                        ) : (
                            <Empty description="No donations yet" />
                        )}
                    </>
                )}
            </Drawer>
        </Card>
    );
}
