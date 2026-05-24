import React, { useState, useEffect } from 'react';
import dayjs from 'dayjs';
import {
    Table, Card, Button, Spin, Alert, Modal, Form, Input, InputNumber,
    Select, message, Space, Popconfirm, Tag, Row, Col, DatePicker, Statistic,
} from 'antd';
import {
    PlusOutlined, EditOutlined, DeleteOutlined, SearchOutlined,
    DollarOutlined, CalendarOutlined, ShopOutlined,
} from '@ant-design/icons';
import {
    useGetExpensesQuery,
    useGetProjectsQuery,
    useCreateExpenseMutation,
    useUpdateExpenseMutation,
    useDeleteExpenseMutation,
} from '../store/apiSlice.js';
import { usePermissions } from '../utils/permissions.js';

const { RangePicker } = DatePicker;

const CATEGORIES = [
    'Supplies', 'Salaries', 'Logistics', 'Equipment',
    'Travel', 'Utilities', 'Rent', 'Marketing', 'Other',
];

const CATEGORY_COLORS = {
    Supplies: 'blue',
    Salaries: 'purple',
    Logistics: 'orange',
    Equipment: 'cyan',
    Travel: 'geekblue',
    Utilities: 'volcano',
    Rent: 'magenta',
    Marketing: 'gold',
    Other: 'default',
};

const STATUS_COLORS = {
    pending: 'gold',
    approved: 'blue',
    paid: 'green',
};

const formatBdt = (n) => `${Number(n || 0).toLocaleString()} POUND`;

export default function ExpensesPage() {
    const { can } = usePermissions();
    const [filters, setFilters] = useState({
        project_id: '', category: '', status: '', from: '', to: '', search: '',
    });
    const [dateRange, setDateRange] = useState(null);

    const queryArgs = { ...filters };
    if (dateRange) {
        queryArgs.from = dateRange[0].format('YYYY-MM-DD');
        queryArgs.to   = dateRange[1].format('YYYY-MM-DD');
    }

    const { data, error, isLoading, isFetching } = useGetExpensesQuery(queryArgs);
    const { data: projectsData, isLoading: isProjectsLoading } = useGetProjectsQuery({});
    const [createExpense, { isLoading: isCreating }] = useCreateExpenseMutation();
    const [updateExpense, { isLoading: isUpdating }] = useUpdateExpenseMutation();
    const [deleteExpense] = useDeleteExpenseMutation();

    const [isModalOpen, setIsModalOpen] = useState(false);
    const [editing, setEditing] = useState(null);
    const [form] = Form.useForm();
    const isEditing = Boolean(editing);

    useEffect(() => {
        if (!isModalOpen) return;
        if (editing) {
            form.setFieldsValue({
                project_id:   editing.project_id,
                category:     editing.category,
                amount:       Number(editing.amount),
                expense_date: editing.expense_date ? dayjs(editing.expense_date) : null,
                vendor:       editing.vendor,
                description:  editing.description,
                status:       editing.status,
            });
        } else {
            form.resetFields();
            form.setFieldsValue({ status: 'approved', expense_date: dayjs() });
        }
    }, [isModalOpen, editing, form]);

    const openCreate = () => { setEditing(null); setIsModalOpen(true); };
    const openEdit   = (row) => { setEditing(row); setIsModalOpen(true); };
    const closeModal = () => { setIsModalOpen(false); setEditing(null); form.resetFields(); };

    const handleFinish = async (values) => {
        const payload = {
            ...values,
            expense_date: values.expense_date ? values.expense_date.format('YYYY-MM-DD') : null,
        };
        try {
            if (isEditing) {
                await updateExpense({ id: editing.id, ...payload }).unwrap();
                message.success('Expense updated');
            } else {
                await createExpense(payload).unwrap();
                message.success('Expense recorded');
            }
            closeModal();
        } catch (err) {
            const errors = err?.data?.errors;
            if (errors) {
                const firstField = Object.keys(errors)[0];
                message.error(errors[firstField][0]);
            } else {
                message.error(err?.data?.message || `Failed to ${isEditing ? 'update' : 'save'} expense`);
            }
        }
    };

    const handleDelete = async (id) => {
        try {
            await deleteExpense(id).unwrap();
            message.success('Expense deleted');
        } catch (err) {
            message.error(err?.data?.message || 'Failed to delete expense');
        }
    };

    const baseColumns = [
        {
            title: 'Date',
            dataIndex: 'expense_date',
            key: 'expense_date',
            render: (d) => d ? dayjs(d).format('YYYY-MM-DD') : '—',
            width: 120,
        },
        {
            title: 'Project',
            key: 'project',
            render: (_, r) => r?.project
                ? <span>{r.project.name} <Tag>{r.project.project_code}</Tag></span>
                : '—',
        },
        {
            title: 'Category',
            dataIndex: 'category',
            key: 'category',
            render: (c) => <Tag color={CATEGORY_COLORS[c] || 'default'}>{c}</Tag>,
        },
        {
            title: 'Amount',
            dataIndex: 'amount',
            key: 'amount',
            align: 'right',
            render: (v) => <strong>{formatBdt(v)}</strong>,
        },
        {
            title: 'Vendor',
            dataIndex: 'vendor',
            key: 'vendor',
            render: (v) => v || <span style={{ color: '#aaa' }}>—</span>,
        },
        {
            title: 'Status',
            dataIndex: 'status',
            key: 'status',
            render: (s) => <Tag color={STATUS_COLORS[s] || 'default'}>{s}</Tag>,
        },
        {
            title: 'Recorded by',
            key: 'creator',
            render: (_, r) => r?.creator?.name || '—',
        },
    ];

    const actionColumn = (can('expenses.edit') || can('expenses.delete')) ? [{
        title: 'Actions',
        key: 'actions',
        width: 180,
        render: (_, record) => (
            <Space>
                {can('expenses.edit') && (
                    <Button size="small" icon={<EditOutlined />} onClick={() => openEdit(record)}>Edit</Button>
                )}
                {can('expenses.delete') && (
                    <Popconfirm
                        title="Delete this expense?"
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
    if (error) return <Alert message="Error" description="Failed to load expenses" type="error" showIcon />;

    const expenses = data?.data?.expenses || [];
    const totals   = data?.data?.totals;

    return (
        <Card
            title="Expenses"
            extra={can('expenses.create') && (
                <Button type="primary" icon={<PlusOutlined />} onClick={openCreate}>Add Expense</Button>
            )}
        >
            <Row gutter={[16, 16]} style={{ marginBottom: 16 }}>
                <Col xs={12} md={8}>
                    <Card size="small">
                        <Statistic title="Total Spent (filtered)" value={totals?.amount || 0} suffix="POUND" valueStyle={{ color: '#cf1322' }} prefix={<DollarOutlined />} />
                    </Card>
                </Col>
                <Col xs={12} md={8}>
                    <Card size="small">
                        <Statistic title="Expense Count" value={totals?.count || 0} />
                    </Card>
                </Col>
            </Row>

            <Row gutter={[12, 12]} style={{ marginBottom: 16 }}>
                <Col xs={24} sm={12} md={6}>
                    <Select
                        placeholder="Filter by Project"
                        allowClear
                        showSearch
                        optionFilterProp="label"
                        style={{ width: '100%' }}
                        loading={isProjectsLoading}
                        value={filters.project_id || undefined}
                        onChange={(v) => setFilters((f) => ({ ...f, project_id: v || '' }))}
                        options={(projectsData?.data || []).map((p) => ({ value: p.id, label: `${p.project_code} — ${p.name}` }))}
                    />
                </Col>
                <Col xs={12} sm={6} md={4}>
                    <Select
                        placeholder="Category"
                        allowClear
                        style={{ width: '100%' }}
                        value={filters.category || undefined}
                        onChange={(v) => setFilters((f) => ({ ...f, category: v || '' }))}
                        options={CATEGORIES.map((c) => ({ value: c, label: c }))}
                    />
                </Col>
                <Col xs={12} sm={6} md={4}>
                    <Select
                        placeholder="Status"
                        allowClear
                        style={{ width: '100%' }}
                        value={filters.status || undefined}
                        onChange={(v) => setFilters((f) => ({ ...f, status: v || '' }))}
                        options={['pending', 'approved', 'paid'].map((s) => ({ value: s, label: s }))}
                    />
                </Col>
                <Col xs={24} sm={12} md={6}>
                    <RangePicker style={{ width: '100%' }} value={dateRange} onChange={setDateRange} />
                </Col>
                <Col xs={24} md={4}>
                    <Input
                        prefix={<SearchOutlined />}
                        placeholder="Vendor / description"
                        allowClear
                        value={filters.search}
                        onChange={(e) => setFilters((f) => ({ ...f, search: e.target.value }))}
                    />
                </Col>
                <Col>
                    <Button onClick={() => { setFilters({ project_id: '', category: '', status: '', from: '', to: '', search: '' }); setDateRange(null); }}>
                        Reset
                    </Button>
                </Col>
            </Row>

            <Table
                dataSource={expenses}
                columns={columns}
                rowKey="id"
                loading={isFetching}
                scroll={{ x: 'max-content' }}
                pagination={{ pageSize: 15 }}
            />

            <Modal
                title={isEditing ? 'Edit Expense' : 'Record New Expense'}
                open={isModalOpen}
                onCancel={closeModal}
                footer={null}
                destroyOnClose
                width={620}
            >
                <Form form={form} layout="vertical" onFinish={handleFinish} style={{ marginTop: 20 }}>
                    <Form.Item
                        name="project_id"
                        label="Project"
                        rules={[{ required: true, message: 'Please select project' }]}
                    >
                        <Select
                            placeholder="Select project this expense belongs to"
                            showSearch
                            optionFilterProp="label"
                            loading={isProjectsLoading}
                            options={(projectsData?.data || []).map((p) => ({ value: p.id, label: `${p.project_code} — ${p.name}` }))}
                        />
                    </Form.Item>

                    <Row gutter={12}>
                        <Col xs={24} sm={12}>
                            <Form.Item
                                name="category"
                                label="Category"
                                rules={[{ required: true, message: 'Please select category' }]}
                            >
                                <Select placeholder="e.g., Supplies" options={CATEGORIES.map((c) => ({ value: c, label: c }))} />
                            </Form.Item>
                        </Col>
                        <Col xs={24} sm={12}>
                            <Form.Item
                                name="amount"
                                label="Amount (POUND)"
                                rules={[{ required: true, message: 'Please enter amount' }]}
                            >
                                <InputNumber min={0.01} step={100} style={{ width: '100%' }} placeholder="0.00" />
                            </Form.Item>
                        </Col>
                    </Row>

                    <Row gutter={12}>
                        <Col xs={24} sm={12}>
                            <Form.Item
                                name="expense_date"
                                label="Expense Date"
                                rules={[{ required: true, message: 'Please select date' }]}
                            >
                                <DatePicker style={{ width: '100%' }} suffixIcon={<CalendarOutlined />} />
                            </Form.Item>
                        </Col>
                        <Col xs={24} sm={12}>
                            <Form.Item
                                name="status"
                                label="Status"
                                rules={[{ required: true, message: 'Please select status' }]}
                            >
                                <Select>
                                    <Select.Option value="pending">Pending</Select.Option>
                                    <Select.Option value="approved">Approved</Select.Option>
                                    <Select.Option value="paid">Paid</Select.Option>
                                </Select>
                            </Form.Item>
                        </Col>
                    </Row>

                    <Form.Item name="vendor" label="Vendor / Payee (optional)">
                        <Input prefix={<ShopOutlined />} placeholder="e.g., Karim Wholesale Rice" />
                    </Form.Item>

                    <Form.Item name="description" label="Description (optional)">
                        <Input.TextArea rows={2} placeholder="What was this expense for?" />
                    </Form.Item>

                    <Form.Item style={{ textAlign: 'right', marginBottom: 0 }}>
                        <Button onClick={closeModal} style={{ marginRight: 8 }}>Cancel</Button>
                        <Button type="primary" htmlType="submit" loading={isCreating || isUpdating}>
                            {isEditing ? 'Update Expense' : 'Save Expense'}
                        </Button>
                    </Form.Item>
                </Form>
            </Modal>
        </Card>
    );
}
