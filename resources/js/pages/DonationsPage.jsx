import React, { useState, useEffect } from 'react';
import {
    Table, Card, Button, Spin, Alert, Modal, Form, Input, InputNumber,
    Select, DatePicker, message, Space, Popconfirm, Tag, Row, Col, Checkbox, Switch, Radio
} from 'antd';
import {
    PlusOutlined, EditOutlined, DeleteOutlined, SearchOutlined, DollarOutlined,
} from '@ant-design/icons';
import dayjs from 'dayjs';
import {
    useGetDonationsQuery,
    useCreateDonationMutation,
    useUpdateDonationMutation,
    useDeleteDonationMutation,
    useGetDonorsQuery,
    useGetProjectsQuery,
    useGetStudentsQuery,
    useGetCampaignsQuery,
} from '../store/apiSlice.js';
import { usePermissions } from '../utils/permissions.js';

const STATUS_OPTIONS = [
    { value: 'pending', label: 'Pending' },
    { value: 'confirmed', label: 'Confirmed' },
    { value: 'failed', label: 'Failed' },
];

const FREQUENCY_OPTIONS = [
    { value: 'weekly',  label: 'Weekly' },
    { value: 'monthly', label: 'Monthly' },
    { value: 'yearly',  label: 'Yearly' },
];

// Quick-pick chips → match by Campaign.name (case-insensitive). Seeded names: Zakat / Fitra / Sadaqah.
const QUICK_CAMPAIGNS = ['Zakat', 'Fitra', 'Sadaqah'];

const statusColor = (s) => {
    switch (s) {
        case 'confirmed': return 'green';
        case 'pending': return 'gold';
        case 'failed': return 'red';
        default: return 'default';
    }
};

const PAYMENT_METHODS = [
    { value: 'Card Payment', label: 'Card Payment' },
    { value: 'Stripe', label: 'Stripe' },
    { value: 'Bank Transfer', label: 'Bank Transfer' },
    { value: 'Cash Payment', label: 'Cash Payment' },
    { value: 'bKash', label: 'bKash' },
    { value: 'Nagad', label: 'Nagad' },
];

const formatBdt = (n) => `${Number(n || 0).toLocaleString()} BDT`;

export default function DonationsPage() {
    const { can } = usePermissions();
    const [filters, setFilters] = useState({ search: '', project_id: '', status: '', campaign_id: '', page: 1 });
    const { data, error, isLoading, isFetching } = useGetDonationsQuery(filters);
    const { data: donorsData, isLoading: isDonorsLoading } = useGetDonorsQuery({});
    const { data: projectsData, isLoading: isProjectsLoading } = useGetProjectsQuery({ status: 'active' });
    const { data: studentsData, isLoading: isStudentsLoading } = useGetStudentsQuery({});
    const { data: campaignsData, isLoading: isCampaignsLoading } = useGetCampaignsQuery({ is_active: true });
    const [createDonation, { isLoading: isCreating }] = useCreateDonationMutation();
    const [updateDonation, { isLoading: isUpdating }] = useUpdateDonationMutation();
    const [deleteDonation] = useDeleteDonationMutation();

    const [isModalOpen, setIsModalOpen] = useState(false);
    const [editing, setEditing] = useState(null);
    const [form] = Form.useForm();
    const isEditing = Boolean(editing);

    useEffect(() => {
        if (!isModalOpen) return;
        if (editing) {
            form.setFieldsValue({
                donor_id: editing.donor_id,
                project_id: editing.project_id,
                student_id: editing.student_id,
                campaign_id: editing.campaign_id,
                amount: editing.amount,
                payment_method: editing.payment_method,
                transaction_date: editing.transaction_date ? dayjs(editing.transaction_date) : null,
                gift_aid: !!editing.gift_aid,
                consent_given: !!editing.consent_given,
                status: editing.status,
                donation_type: editing.is_recurring ? 'recurring' : 'on-off',
                recurrence_frequency: editing.recurrence_frequency || 'monthly',
                recurrence_next_at:   editing.recurrence_next_at ? dayjs(editing.recurrence_next_at) : null,
                recurrence_ends_at:   editing.recurrence_ends_at ? dayjs(editing.recurrence_ends_at) : null,
            });
        } else {
            form.resetFields();
            form.setFieldsValue({
                payment_method: 'Card Payment',
                status: 'confirmed',
                transaction_date: dayjs(),
                donation_type: 'on-off',
                recurrence_frequency: 'monthly',
            });
        }
    }, [isModalOpen, editing, form]);

    const openCreate = () => { setEditing(null); setIsModalOpen(true); };
    const openEdit = (row) => { setEditing(row); setIsModalOpen(true); };
    const closeModal = () => { setIsModalOpen(false); setEditing(null); form.resetFields(); };

    const handleFinish = async (values) => {
        const isRecurring = values.donation_type === 'recurring';
        const { donation_type, other_enabled, ...rest } = values;

        const payload = {
            ...rest,
            transaction_date: values.transaction_date
                ? values.transaction_date.format('YYYY-MM-DD HH:mm:ss')
                : dayjs().format('YYYY-MM-DD HH:mm:ss'),
            is_recurring: isRecurring,
            recurrence_frequency: isRecurring ? values.recurrence_frequency : null,
            recurrence_next_at: isRecurring && values.recurrence_next_at
                ? values.recurrence_next_at.format('YYYY-MM-DD HH:mm:ss')
                : null,
            recurrence_ends_at: isRecurring && values.recurrence_ends_at
                ? values.recurrence_ends_at.format('YYYY-MM-DD HH:mm:ss')
                : null,
        };
        try {
            if (isEditing) {
                await updateDonation({ id: editing.id, ...payload }).unwrap();
                message.success('Donation updated successfully!');
            } else {
                await createDonation(payload).unwrap();
                message.success('Donation recorded successfully!');
            }
            closeModal();
        } catch (err) {
            const errors = err?.data?.errors;
            if (errors) {
                const firstField = Object.keys(errors)[0];
                message.error(errors[firstField][0]);
            } else {
                message.error(err?.data?.message || `Failed to ${isEditing ? 'update' : 'record'} donation`);
            }
        }
    };

    const handleDelete = async (id) => {
        try {
            await deleteDonation(id).unwrap();
            message.success('Donation deleted');
        } catch (err) {
            message.error(err?.data?.message || 'Failed to delete donation');
        }
    };

    const baseColumns = [
        {
            title: 'Receipt',
            dataIndex: 'receipt_number',
            key: 'receipt_number',
            render: (v) => <Tag color="purple">{v}</Tag>,
        },
        {
            title: 'Donor',
            key: 'donor',
            render: (_, r) => r.donor?.name || '—',
        },
        {
            title: 'Project',
            key: 'project',
            render: (_, r) => r.project?.name || '—',
        },
        {
            title: 'Student',
            key: 'student',
            render: (_, r) => r.student?.student_name
                ? <Tag color="blue">{r.student.student_name}</Tag>
                : <Tag color="default">General</Tag>,
        },
        {
            title: 'Campaign',
            key: 'campaign',
            render: (_, r) => r.campaign?.name
                ? <Tag color={r.is_recurring ? 'purple' : 'cyan'}>{r.campaign.name}{r.is_recurring ? ` · ${r.recurrence_frequency || ''}` : ''}</Tag>
                : <Tag color="default">—</Tag>,
        },
        {
            title: 'Amount',
            dataIndex: 'amount',
            key: 'amount',
            render: (a) => <strong>{formatBdt(a)}</strong>,
        },
        { title: 'Method', dataIndex: 'payment_method', key: 'payment_method' },
        {
            title: 'Date',
            dataIndex: 'transaction_date',
            key: 'transaction_date',
            render: (d) => d ? dayjs(d).format('YYYY-MM-DD') : '—',
        },
        {
            title: 'Gift Aid',
            dataIndex: 'gift_aid',
            key: 'gift_aid',
            render: (v) => v ? <Tag color="green">Yes</Tag> : <Tag color="default">No</Tag>,
        },
        {
            title: 'Consent',
            dataIndex: 'consent_given',
            render: (v) => v ? <Tag color="blue">Yes</Tag> : <Tag color="default">No</Tag>,
        },
        {
            title: 'Status',
            dataIndex: 'status',
            key: 'status',
            render: (s) => <Tag color={statusColor(s)}>{s}</Tag>,
        },
    ];

    const actionColumn = [{
        title: 'Actions',
        key: 'actions',
        width: 180,
        render: (_, record) => (
            <Space>
                {can('donations.edit') && (
                    <Button size="small" icon={<EditOutlined />} onClick={() => openEdit(record)}>Edit</Button>
                )}
                {can('donations.delete') && (
                    <Popconfirm
                        title="Delete this donation?"
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
    if (error) return <Alert message="Error" description="Failed to load donations" type="error" showIcon />;

    const pagination = data?.data
        ? {
            current: data.data.current_page,
            pageSize: data.data.per_page,
            total: data.data.total,
            onChange: (page) => setFilters((f) => ({ ...f, page })),
        }
        : false;

    return (
        <Card
            title="Donations"
            extra={can('donations.create') && (
                <Button type="primary" icon={<PlusOutlined />} onClick={openCreate}>Record Donation</Button>
            )}
        >
            <Row gutter={[12, 12]} style={{ marginBottom: 16 }}>
                <Col xs={24} sm={12} md={8}>
                    <Input
                        prefix={<SearchOutlined />}
                        placeholder="Search by receipt or donor name"
                        allowClear
                        value={filters.search}
                        onChange={(e) => setFilters((f) => ({ ...f, search: e.target.value, page: 1 }))}
                    />
                </Col>
                <Col xs={12} sm={6} md={5}>
                    <Select
                        placeholder="Project"
                        allowClear
                        style={{ width: '100%' }}
                        loading={isProjectsLoading}
                        value={filters.project_id || undefined}
                        onChange={(v) => setFilters((f) => ({ ...f, project_id: v || '', page: 1 }))}
                        options={(projectsData?.data || []).map((p) => ({ value: p.id, label: p.name }))}
                    />
                </Col>
                <Col xs={12} sm={6} md={4}>
                    <Select
                        placeholder="Status"
                        allowClear
                        style={{ width: '100%' }}
                        value={filters.status || undefined}
                        onChange={(v) => setFilters((f) => ({ ...f, status: v || '', page: 1 }))}
                        options={STATUS_OPTIONS}
                    />
                </Col>
                <Col xs={12} sm={6} md={5}>
                    <Select
                        placeholder="Campaign"
                        allowClear
                        style={{ width: '100%' }}
                        loading={isCampaignsLoading}
                        value={filters.campaign_id || undefined}
                        onChange={(v) => setFilters((f) => ({ ...f, campaign_id: v || '', page: 1 }))}
                        options={(campaignsData?.data || []).map((c) => ({ value: c.id, label: c.name }))}
                    />
                </Col>
                <Col>
                    <Button onClick={() => setFilters({ search: '', project_id: '', status: '', campaign_id: '', page: 1 })}>Reset</Button>
                </Col>
            </Row>

            <Table
                dataSource={data?.data?.data || []}
                columns={columns}
                rowKey="id"
                loading={isFetching}
                pagination={pagination}
                scroll={{ x: 'max-content' }}
            />

            <Modal
                title={isEditing ? `Edit Donation: ${editing?.receipt_number}` : 'Record New Donation'}
                open={isModalOpen}
                onCancel={closeModal}
                footer={null}
                destroyOnClose
                width={1000}
                centered
            >
                {!isEditing && (
                    <Alert
                        type="info"
                        showIcon
                        message="Receipt number will be auto-generated (REC-100001, REC-100002, …)"
                        style={{ marginBottom: 16 }}
                    />
                )}
                <Form form={form} layout="vertical" onFinish={handleFinish}>
                    <Row gutter={24}>
                        {/* বাম দিকের কলাম */}
                        <Col span={16}>
                          <div style={{ border: '1px solid #f0f0f0', borderRadius: '4px', padding: '16px', marginBottom: '16px' }}>
                            <Row gutter={12}>
                                <Col xs={24} sm={12}>
                                    <Form.Item
                                        name="donor_id"
                                        label="Donor"
                                        rules={[{ required: true, message: 'Please select donor' }]}
                                    >
                                        <Select
                                            placeholder="Select donor"
                                            loading={isDonorsLoading}
                                            showSearch
                                            optionFilterProp="label"
                                            disabled={isEditing}
                                            options={(donorsData?.data || []).map((d) => ({
                                                value: d.id,
                                                label: `${d.name} (${d.donor_id_code})`,
                                            }))}
                                        />
                                    </Form.Item>
                                </Col>
                                <Col xs={24} sm={12}>
                                    <Form.Item
                                        name="project_id"
                                        label="Project"
                                        rules={[{ required: true, message: 'Please select project' }]}
                                    >
                                        <Select
                                            placeholder="Select project"
                                            loading={isProjectsLoading}
                                            showSearch
                                            optionFilterProp="label"
                                            options={(projectsData?.data || []).map((p) => ({
                                                value: p.id,
                                                label: `${p.name} (${p.project_code})`,
                                            }))}
                                        />
                                    </Form.Item>
                                </Col>
                            </Row>

                            <Form.Item name="student_id" label="Link to Student (optional)">
                                <Select
                                    placeholder="Select student"
                                    allowClear
                                    loading={isStudentsLoading}
                                    showSearch
                                    optionFilterProp="label"
                                    options={(studentsData?.data || []).map((s) => ({
                                        value: s.id,
                                        label: `${s.student_name} (${s.student_id})`,
                                    }))}
                                />
                            </Form.Item>

                            <Row gutter={12}>
                                <Col xs={24} sm={12}>
                                    <Form.Item
                                        name="amount"
                                        label="Amount"
                                        rules={[{ required: true, message: 'Please enter amount' }]}
                                    >
                                        <InputNumber
                                            min={1}
                                            style={{ width: '100%' }}
                                            prefix={<DollarOutlined />}
                                            placeholder="0.00"
                                        />
                                    </Form.Item>
                                </Col>
                                <Col xs={24} sm={12}>
                                    <Form.Item
                                        name="payment_method"
                                        label="Payment Method"
                                        rules={[{ required: true, message: 'Please select method' }]}
                                    >
                                        <Select options={PAYMENT_METHODS} />
                                    </Form.Item>
                                </Col>
                            </Row>

                            <Row gutter={12}>
                                <Col xs={24} sm={12}>
                                    <Form.Item
                                        name="transaction_date"
                                        label="Transaction Date"
                                        rules={[{ required: true, message: 'Please select date' }]}
                                    >
                                        <DatePicker showTime style={{ width: '100%' }} />
                                    </Form.Item>
                                </Col>
                                <Col xs={24} sm={12}>
                                    <Form.Item name="status" label="Status">
                                        <Select options={STATUS_OPTIONS} />
                                    </Form.Item>
                                </Col>
                            </Row>
                            {/* Gift Aid Section */}
                            <Form.Item name="gift_aid" label="Gift Aid" valuePropName="checked">
                                <Switch checkedChildren="Yes" unCheckedChildren="No" />
                            </Form.Item>
                            <p style={{ fontSize: '12px', color: '#888', marginTop: '-15px', marginBottom: '15px' }}>
                                I am a UK taxpayer and I wish (Inspired By Islam) to reclaim tax back on all donations
                                I have made within the last 6 years and all donations that I make hereafter. *
                            </p>

                            {/* Consent Section */}
                            <Form.Item name="consent_given" label="Consent" valuePropName="checked">
                                <Switch checkedChildren="Yes" unCheckedChildren="No" />
                            </Form.Item>
                            <p style={{ fontSize: '12px', color: '#888', marginTop: '-15px', marginBottom: '15px' }}>
                                Donor give consent for personal data to be used for future communication,
                                but not to share with third party
                            </p>
                            <Form.Item style={{ textAlign: 'right', marginBottom: 0 }}>
                                <Button onClick={closeModal} style={{ marginRight: 8 }}>Cancel</Button>
                                <Button type="primary" htmlType="submit" loading={isCreating || isUpdating}>
                                    {isEditing ? 'Update Donation' : 'Save Donation'}
                                </Button>
                            </Form.Item>
                            </div>
                        </Col>

                        {/* Right column: Campaign + recurrence */}
                        <Col span={8}>
                            <div style={{ border: '1px solid #f0f0f0', borderRadius: '4px', padding: '16px', marginBottom: '16px' }}>
                                <Form.Item label="Select Campaign" name="campaign_id" style={{ marginBottom: '12px' }}>
                                    <Select
                                        placeholder="Select Campaign"
                                        allowClear
                                        loading={isCampaignsLoading}
                                        showSearch
                                        optionFilterProp="label"
                                        options={(campaignsData?.data || []).map((c) => ({
                                            value: c.id,
                                            label: `${c.name}${c.type === 'recurring' ? ' (recurring)' : ''}`,
                                        }))}
                                        onChange={(id) => {
                                            const c = (campaignsData?.data || []).find((x) => x.id === id);
                                            if (!c) return;
                                            const patch = { donation_type: c.type === 'recurring' ? 'recurring' : 'on-off' };
                                            if (c.default_amount && !form.getFieldValue('amount')) {
                                                patch.amount = Number(c.default_amount);
                                            }
                                            form.setFieldsValue(patch);
                                        }}
                                    />
                                </Form.Item>

                                <div style={{ textAlign: 'center', marginBottom: '12px' }}>
                                    <Form.Item name="donation_type" style={{ marginBottom: 0 }}>
                                        <Radio.Group buttonStyle="solid">
                                            <Radio.Button value="on-off">On-Off</Radio.Button>
                                            <Radio.Button value="recurring">Recurring</Radio.Button>
                                        </Radio.Group>
                                    </Form.Item>
                                </div>

                                {/* "Other" custom-amount row — when ticked, the numeric input drives the main amount */}
                                <Row gutter={8} align="middle" style={{ marginBottom: '12px' }}>
                                    <Col span={8}>
                                        <Form.Item name="other_enabled" valuePropName="checked" style={{ marginBottom: 0 }}>
                                            <Checkbox>Other</Checkbox>
                                        </Form.Item>
                                    </Col>
                                    <Col span={16}>
                                        <Form.Item shouldUpdate={(p, n) => p.other_enabled !== n.other_enabled} noStyle>
                                            {({ getFieldValue }) => (
                                                <InputNumber
                                                    style={{ width: '100%' }}
                                                    min={0}
                                                    placeholder="0"
                                                    disabled={!getFieldValue('other_enabled')}
                                                    value={form.getFieldValue('amount')}
                                                    onChange={(v) => form.setFieldsValue({ amount: v })}
                                                />
                                            )}
                                        </Form.Item>
                                    </Col>
                                </Row>

                                <Form.Item shouldUpdate={(p, n) => p.donation_type !== n.donation_type} noStyle>
                                    {({ getFieldValue }) => getFieldValue('donation_type') === 'recurring' && (
                                        <>
                                            <Form.Item
                                                name="recurrence_frequency"
                                                label="Frequency"
                                                rules={[{ required: true, message: 'Pick a frequency' }]}
                                                style={{ marginBottom: '8px' }}
                                            >
                                                <Select options={FREQUENCY_OPTIONS} />
                                            </Form.Item>
                                            <Form.Item
                                                name="recurrence_next_at"
                                                label="First charge"
                                                rules={[{ required: true, message: 'Pick a start date' }]}
                                                style={{ marginBottom: '8px' }}
                                            >
                                                <DatePicker showTime style={{ width: '100%' }} />
                                            </Form.Item>
                                            <Form.Item name="recurrence_ends_at" label="Ends at (optional)" style={{ marginBottom: '12px' }}>
                                                <DatePicker showTime style={{ width: '100%' }} />
                                            </Form.Item>
                                        </>
                                    )}
                                </Form.Item>

                                {/* Quick-pick chips: set campaign_id to seeded Zakat/Fitra/Sadaqah */}
                                <div style={{ marginBottom: '12px' }}>
                                    <Radio.Group
                                        buttonStyle="solid"
                                        style={{ display: 'flex' }}
                                        value={(() => {
                                            const id = form.getFieldValue('campaign_id');
                                            const c = (campaignsData?.data || []).find((x) => x.id === id);
                                            return c && QUICK_CAMPAIGNS.includes(c.name) ? c.name : undefined;
                                        })()}
                                        onChange={(e) => {
                                            const c = (campaignsData?.data || []).find((x) => x.name === e.target.value);
                                            if (c) form.setFieldsValue({
                                                campaign_id: c.id,
                                                donation_type: c.type === 'recurring' ? 'recurring' : 'on-off',
                                            });
                                        }}
                                    >
                                        {QUICK_CAMPAIGNS.map((name) => (
                                            <Radio.Button key={name} value={name} style={{ flex: 1, textAlign: 'center' }}>{name}</Radio.Button>
                                        ))}
                                    </Radio.Group>
                                </div>

                                <Form.Item shouldUpdate={(p, n) => p.amount !== n.amount} noStyle>
                                    {({ getFieldValue }) => (
                                        <div style={{ background: '#f9f9f9', padding: '8px', textAlign: 'center', color: '#ff4d4f', fontWeight: 'bold' }}>
                                            {formatBdt(getFieldValue('amount'))}
                                        </div>
                                    )}
                                </Form.Item>
                            </div>
                        </Col>
                    </Row>
                </Form>
            </Modal>
        </Card>
    );
}
