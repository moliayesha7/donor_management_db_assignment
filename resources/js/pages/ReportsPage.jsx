import React, { useState } from 'react';
import {
    Card, Tabs, Table, Tag, Spin, Alert, Row, Col, Statistic,
    Select, Button, Empty, Progress, Space, Typography, DatePicker, Divider,
} from 'antd';
import {
    FileExcelOutlined, ProjectOutlined, DollarOutlined, TeamOutlined,
    LineChartOutlined, ReloadOutlined, BankOutlined, AccountBookOutlined,
    PieChartOutlined, SwapOutlined,
} from '@ant-design/icons';
import {
    useGetProjectWiseReportQuery,
    useLazyGetProjectReportDetailQuery,
    useGetDonationSummaryQuery,
    useGetProjectsQuery,
    useGetProjectTypesQuery,
    useGetCashFlowReportQuery,
    useGetDonationLedgerQuery,
    useGetProjectBalanceReportQuery,
    useGetFinancialReconciliationReportQuery,
} from '../store/apiSlice.js';

const { Text, Title } = Typography;
const { RangePicker } = DatePicker;

const STATUS_COLORS = {
    pending: 'gold',
    active: 'green',
    completed: 'blue',
    suspended: 'red',
};

const formatBdt = (n) => `${Number(n || 0).toLocaleString()} BDT`;

/** Tiny CSV exporter — handles values containing commas/quotes/newlines. */
const downloadCsv = (filename, rows) => {
    if (!rows?.length) return;
    const headers = Object.keys(rows[0]);
    const escape = (v) => {
        if (v === null || v === undefined) return '';
        const s = String(v);
        return /[",\n]/.test(s) ? `"${s.replace(/"/g, '""')}"` : s;
    };
    const csv = [
        headers.join(','),
        ...rows.map((r) => headers.map((h) => escape(r[h])).join(',')),
    ].join('\n');
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    a.click();
    URL.revokeObjectURL(url);
};

/* ========================================================================== */
/* Tab 1: Project-wise Report                                                  */
/* ========================================================================== */
function ProjectWiseTab({ onOpenDetail }) {
    const [filters, setFilters] = useState({ status: '', project_type_id: '' });
    const { data, isLoading, isFetching, error, refetch } = useGetProjectWiseReportQuery(filters);
    const { data: typesData } = useGetProjectTypesQuery({ status: 'active' });

    const rows = data?.data?.projects || [];
    const totals = data?.data?.totals;

    if (isLoading) return <div style={{ textAlign: 'center', marginTop: 50 }}><Spin /></div>;
    if (error) return <Alert message="Failed to load project-wise report" type="error" showIcon />;

    return (
        <>
            <Row gutter={[16, 16]} style={{ marginBottom: 16 }}>
                <Col xs={12} md={4}>
                    <Card><Statistic title="Projects" value={totals?.project_count || 0} prefix={<ProjectOutlined />} /></Card>
                </Col>
                <Col xs={12} md={5}>
                    <Card><Statistic title="Budget Total" value={totals?.budget_total || 0} suffix="BDT" /></Card>
                </Col>
                <Col xs={12} md={5}>
                    <Card><Statistic title="Donations Raised" value={totals?.donations_total || 0} suffix="BDT" valueStyle={{ color: '#3f8600' }} /></Card>
                </Col>
                <Col xs={12} md={5}>
                    <Card><Statistic title="Expenses Total" value={totals?.expenses_total || 0} suffix="BDT" valueStyle={{ color: '#cf1322' }} /></Card>
                </Col>
                <Col xs={12} md={5}>
                    <Card><Statistic title="Budget Remaining" value={totals?.remaining_total || 0} suffix="BDT" valueStyle={{ color: (totals?.remaining_total || 0) < 0 ? '#cf1322' : undefined }} /></Card>
                </Col>
            </Row>

            <Row gutter={[12, 12]} style={{ marginBottom: 16 }}>
                <Col xs={12} md={6}>
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
                <Col xs={12} md={6}>
                    <Select
                        placeholder="Project Type"
                        allowClear
                        style={{ width: '100%' }}
                        value={filters.project_type_id || undefined}
                        onChange={(v) => setFilters((f) => ({ ...f, project_type_id: v || '' }))}
                        options={(typesData?.data || []).map((t) => ({ value: t.id, label: t.name }))}
                    />
                </Col>
                <Col flex="auto" style={{ textAlign: 'right' }}>
                    <Space>
                        <Button icon={<ReloadOutlined />} onClick={refetch}>Refresh</Button>
                        <Button
                            icon={<FileExcelOutlined />}
                            disabled={!rows.length}
                            onClick={() => downloadCsv(`project-wise-report-${new Date().toISOString().slice(0,10)}.csv`, rows)}
                        >
                            Export CSV
                        </Button>
                    </Space>
                </Col>
            </Row>

            <Table
                dataSource={rows}
                rowKey="id"
                loading={isFetching}
                scroll={{ x: 'max-content' }}
                columns={[
                    { title: 'Code', dataIndex: 'project_code', key: 'project_code' },
                    { title: 'Project', dataIndex: 'name', key: 'name' },
                    { title: 'Type', dataIndex: 'type', key: 'type', render: (t) => t || '-' },
                    {
                        title: 'Status',
                        dataIndex: 'status',
                        key: 'status',
                        render: (s) => <Tag color={STATUS_COLORS[s] || 'default'}>{s}</Tag>,
                    },
                    {
                        title: 'Budget',
                        dataIndex: 'budget',
                        key: 'budget',
                        align: 'right',
                        render: formatBdt,
                    },
                    {
                        title: 'Raised',
                        dataIndex: 'donations_total',
                        key: 'donations_total',
                        align: 'right',
                        render: (v) => <Text strong style={{ color: '#3f8600' }}>{formatBdt(v)}</Text>,
                    },
                    {
                        title: 'Expenses',
                        dataIndex: 'expenses_total',
                        key: 'expenses_total',
                        align: 'right',
                        render: (v) => <Text style={{ color: '#cf1322' }}>{formatBdt(v)}</Text>,
                    },
                    {
                        title: 'Remaining',
                        dataIndex: 'remaining',
                        key: 'remaining',
                        align: 'right',
                        render: (v) => (
                            <Text style={{ color: v < 0 ? '#cf1322' : undefined }}>{formatBdt(v)}</Text>
                        ),
                    },
                    {
                        title: 'Funded',
                        dataIndex: 'funded_percent',
                        key: 'funded_percent',
                        width: 130,
                        render: (p) => (
                            <Progress
                                percent={Math.min(p, 100)}
                                size="small"
                                status={p >= 100 ? 'success' : 'active'}
                                format={() => `${p}%`}
                            />
                        ),
                    },
                    {
                        title: 'Spent',
                        dataIndex: 'spent_percent',
                        key: 'spent_percent',
                        width: 130,
                        render: (p) => (
                            <Progress
                                percent={Math.min(p, 100)}
                                size="small"
                                status={p > 100 ? 'exception' : 'normal'}
                                strokeColor={p > 100 ? '#cf1322' : '#fa8c16'}
                                format={() => `${p}%`}
                            />
                        ),
                    },
                    { title: 'Donors',   dataIndex: 'donor_count',   key: 'donor_count',   align: 'right' },
                    { title: 'Students', dataIndex: 'student_count', key: 'student_count', align: 'right' },
                    {
                        title: 'Detail',
                        key: 'detail',
                        render: (_, r) => (
                            <Button size="small" type="link" onClick={() => onOpenDetail(r.id)}>View detail →</Button>
                        ),
                    },
                ]}
            />
        </>
    );
}

/* ========================================================================== */
/* Tab 2: Project Detail                                                       */
/* ========================================================================== */
function ProjectDetailTab({ projectId, onProjectChange }) {
    const { data: projectsData } = useGetProjectsQuery({});
    const [trigger, { data, isLoading, isFetching, error }] = useLazyGetProjectReportDetailQuery();

    React.useEffect(() => {
        if (projectId) trigger(projectId);
    }, [projectId, trigger]);

    const detail = data?.data;

    return (
        <>
            <Row gutter={[12, 12]} style={{ marginBottom: 16 }}>
                <Col xs={24} md={12}>
                    <Select
                        placeholder="Select a project to view detailed report"
                        style={{ width: '100%' }}
                        showSearch
                        optionFilterProp="label"
                        value={projectId || undefined}
                        onChange={onProjectChange}
                        options={(projectsData?.data || []).map((p) => ({
                            value: p.id,
                            label: `${p.project_code} — ${p.name}`,
                        }))}
                    />
                </Col>
                {detail && (
                    <Col flex="auto" style={{ textAlign: 'right' }}>
                        <Space>
                            <Button
                                icon={<FileExcelOutlined />}
                                onClick={() => downloadCsv(
                                    `project-${detail.project.project_code}-donors.csv`,
                                    detail.donors,
                                )}
                            >
                                Export Donors
                            </Button>
                            <Button
                                icon={<FileExcelOutlined />}
                                onClick={() => downloadCsv(
                                    `project-${detail.project.project_code}-students.csv`,
                                    detail.students,
                                )}
                            >
                                Export Students
                            </Button>
                        </Space>
                    </Col>
                )}
            </Row>

            {!projectId && <Empty description="Choose a project above to load the detailed report" />}
            {isLoading && <div style={{ textAlign: 'center', marginTop: 40 }}><Spin /></div>}
            {error && <Alert message="Failed to load project detail" type="error" showIcon />}

            {detail && !isFetching && (
                <>
                    <Card size="small" style={{ marginBottom: 16 }}>
                        <Row gutter={[16, 16]}>
                            <Col xs={24} md={12}>
                                <Title level={4} style={{ margin: 0 }}>
                                    {detail.project.name}
                                </Title>
                                <Text type="secondary">{detail.project.project_code}</Text>{' '}
                                <Tag color={STATUS_COLORS[detail.project.status] || 'default'}>{detail.project.status}</Tag>{' '}
                                <Tag>{detail.project.type || '—'}</Tag>
                                {detail.project.description && (
                                    <div style={{ marginTop: 8 }}>
                                        <Text>{detail.project.description}</Text>
                                    </div>
                                )}
                            </Col>
                            <Col xs={24} md={12}>
                                <Row gutter={8}>
                                    <Col span={8}><Statistic title="Budget" value={detail.project.budget} suffix="BDT" /></Col>
                                    <Col span={8}><Statistic title="Raised" value={detail.summary.donations_total} suffix="BDT" valueStyle={{ color: '#3f8600' }} /></Col>
                                    <Col span={8}><Statistic title="Expenses" value={detail.summary.expenses_total} suffix="BDT" valueStyle={{ color: '#cf1322' }} /></Col>
                                    <Col span={8}><Statistic title="Remaining" value={detail.summary.remaining} suffix="BDT" valueStyle={{ color: detail.summary.remaining < 0 ? '#cf1322' : undefined }} /></Col>
                                    <Col span={8}><Statistic title="Funded %" value={detail.summary.funded_percent} suffix="%" /></Col>
                                    <Col span={8}><Statistic title="Spent %" value={detail.summary.spent_percent} suffix="%" /></Col>
                                </Row>
                            </Col>
                        </Row>
                    </Card>

                    <Title level={5}><TeamOutlined /> Donor List ({detail.donors.length})</Title>
                    {detail.donors.length ? (
                        <Table
                            size="small"
                            dataSource={detail.donors}
                            rowKey="id"
                            pagination={{ pageSize: 10 }}
                            scroll={{ x: 'max-content' }}
                            columns={[
                                { title: 'Donor ID', dataIndex: 'donor_id_code', key: 'donor_id_code', render: (c) => <Tag color="purple">{c}</Tag> },
                                { title: 'Name', dataIndex: 'name', key: 'name' },
                                { title: 'Phone', dataIndex: 'phone_number', key: 'phone_number' },
                                { title: 'Email', dataIndex: 'email', key: 'email', render: (e) => e || '—' },
                                { title: '# Donations', dataIndex: 'donation_count', key: 'donation_count', align: 'right' },
                                { title: 'Total Contributed', dataIndex: 'total_contributed', key: 'total_contributed', align: 'right', render: formatBdt },
                                { title: 'Last Donation', dataIndex: 'last_donation_at', key: 'last_donation_at', render: (d) => d ? new Date(d).toLocaleDateString() : '—' },
                            ]}
                        />
                    ) : <Empty description="No donors yet" />}

                    <Divider />

                    <Title level={5}><ProjectOutlined /> Student List ({detail.students.length})</Title>
                    {detail.students.length ? (
                        <Table
                            size="small"
                            dataSource={detail.students}
                            rowKey="id"
                            pagination={{ pageSize: 10 }}
                            scroll={{ x: 'max-content' }}
                            columns={[
                                { title: 'Student ID', dataIndex: 'student_id', key: 'student_id', render: (c) => <Tag color="cyan">{c}</Tag> },
                                { title: 'Name', dataIndex: 'student_name', key: 'student_name' },
                                { title: 'Guardian', dataIndex: 'guardian_name', key: 'guardian_name', render: (g) => g || '—' },
                                { title: 'Funding Status', dataIndex: 'funding_status', key: 'funding_status', render: (s) => s ? <Tag>{s}</Tag> : '—' },
                                { title: '# Donations', dataIndex: 'donation_count', key: 'donation_count', align: 'right' },
                                { title: 'Total Received', dataIndex: 'total_received', key: 'total_received', align: 'right', render: formatBdt },
                            ]}
                        />
                    ) : <Empty description="No students linked to this project yet" />}

                    <Divider />

                    <Title level={5}><DollarOutlined /> Recent Donations</Title>
                    {detail.recent_donations.length ? (
                        <Table
                            size="small"
                            dataSource={detail.recent_donations}
                            rowKey="id"
                            pagination={{ pageSize: 10 }}
                            scroll={{ x: 'max-content' }}
                            columns={[
                                { title: 'Date', dataIndex: 'transaction_date', key: 'transaction_date', render: (d) => d ? new Date(d).toLocaleDateString() : '—' },
                                { title: 'Donor', key: 'donor', render: (_, r) => r.donor ? `${r.donor.name} (${r.donor.donor_id_code})` : '—' },
                                { title: 'Student', key: 'student', render: (_, r) => r.student?.student_name || '—' },
                                { title: 'Amount', dataIndex: 'amount', key: 'amount', align: 'right', render: formatBdt },
                                { title: 'Method', dataIndex: 'payment_method', key: 'payment_method' },
                                { title: 'Receipt', dataIndex: 'receipt_number', key: 'receipt_number' },
                                {
                                    title: 'Status', dataIndex: 'status', key: 'status',
                                    render: (s) => <Tag color={s === 'confirmed' ? 'green' : s === 'failed' ? 'red' : 'gold'}>{s}</Tag>,
                                },
                            ]}
                        />
                    ) : <Empty description="No donations yet" />}

                    <Divider />

                    <Title level={5}><DollarOutlined /> Expense Breakdown (by Category)</Title>
                    {detail.expense_breakdown?.length ? (
                        <Row gutter={[12, 12]} style={{ marginBottom: 16 }}>
                            {detail.expense_breakdown.map((b) => {
                                const total = Number(detail.summary.expenses_total) || 1;
                                const pct = (Number(b.total_amount) / total) * 100;
                                return (
                                    <Col key={b.category} xs={24} sm={12} md={8}>
                                        <Card size="small">
                                            <Space direction="vertical" style={{ width: '100%' }} size={4}>
                                                <div style={{ display: 'flex', justifyContent: 'space-between' }}>
                                                    <Text strong>{b.category}</Text>
                                                    <Text type="secondary">{b.expense_count} item(s)</Text>
                                                </div>
                                                <Text style={{ color: '#cf1322', fontSize: 18 }}>{formatBdt(b.total_amount)}</Text>
                                                <Progress percent={Math.round(pct)} size="small" strokeColor="#fa8c16" />
                                            </Space>
                                        </Card>
                                    </Col>
                                );
                            })}
                        </Row>
                    ) : <Empty description="No expenses recorded for this project yet" />}

                    <Title level={5}><DollarOutlined /> Recent Expenses</Title>
                    {detail.recent_expenses?.length ? (
                        <Table
                            size="small"
                            dataSource={detail.recent_expenses}
                            rowKey="id"
                            pagination={{ pageSize: 10 }}
                            scroll={{ x: 'max-content' }}
                            columns={[
                                { title: 'Date', dataIndex: 'expense_date', key: 'expense_date', render: (d) => d ? new Date(d).toLocaleDateString() : '—' },
                                { title: 'Category', dataIndex: 'category', key: 'category', render: (c) => <Tag>{c}</Tag> },
                                { title: 'Vendor', dataIndex: 'vendor', key: 'vendor', render: (v) => v || '—' },
                                { title: 'Description', dataIndex: 'description', key: 'description', ellipsis: true },
                                { title: 'Amount', dataIndex: 'amount', key: 'amount', align: 'right', render: (v) => <Text style={{ color: '#cf1322' }}>{formatBdt(v)}</Text> },
                                {
                                    title: 'Status', dataIndex: 'status', key: 'status',
                                    render: (s) => <Tag color={s === 'paid' ? 'green' : s === 'approved' ? 'blue' : 'gold'}>{s}</Tag>,
                                },
                                { title: 'Recorded by', key: 'creator', render: (_, r) => r.creator?.name || '—' },
                            ]}
                        />
                    ) : null}
                </>
            )}
        </>
    );
}

/* ========================================================================== */
/* Tab 3: Donation Summary                                                     */
/* ========================================================================== */
function DonationSummaryTab() {
    const [dateRange, setDateRange] = useState(null);
    const params = dateRange ? {
        from: dateRange[0].format('YYYY-MM-DD'),
        to:   dateRange[1].format('YYYY-MM-DD'),
    } : {};
    const { data, isLoading, isFetching, error, refetch } = useGetDonationSummaryQuery(params);

    if (isLoading) return <div style={{ textAlign: 'center', marginTop: 50 }}><Spin /></div>;
    if (error) return <Alert message="Failed to load donation summary" type="error" showIcon />;

    const d = data?.data;

    return (
        <>
            <Row gutter={[16, 16]} style={{ marginBottom: 16 }}>
                <Col xs={12} md={8}>
                    <Card><Statistic title="Total Donations" value={d?.totals?.donations_total || 0} suffix="BDT" valueStyle={{ color: '#3f8600' }} /></Card>
                </Col>
                <Col xs={12} md={8}>
                    <Card><Statistic title="Donation Count" value={d?.totals?.donation_count || 0} /></Card>
                </Col>
                <Col xs={24} md={8}>
                    <Card><Statistic title="Unique Donors" value={d?.totals?.unique_donors || 0} prefix={<TeamOutlined />} /></Card>
                </Col>
            </Row>

            <Row gutter={[12, 12]} style={{ marginBottom: 16 }}>
                <Col xs={24} md={12}>
                    <RangePicker
                        style={{ width: '100%' }}
                        onChange={setDateRange}
                        value={dateRange}
                    />
                </Col>
                <Col flex="auto" style={{ textAlign: 'right' }}>
                    <Space>
                        <Button icon={<ReloadOutlined />} onClick={refetch}>Refresh</Button>
                        <Button
                            icon={<FileExcelOutlined />}
                            disabled={!d?.top_donors?.length}
                            onClick={() => downloadCsv(`top-donors-${new Date().toISOString().slice(0,10)}.csv`, d.top_donors)}
                        >
                            Export Top Donors
                        </Button>
                    </Space>
                </Col>
            </Row>

            <Row gutter={[16, 16]}>
                <Col xs={24} md={12}>
                    <Card title={<><LineChartOutlined /> Monthly (last 12 months)</>} size="small">
                        {d?.monthly?.length ? (
                            <Table
                                size="small"
                                dataSource={d.monthly}
                                rowKey="period"
                                pagination={false}
                                columns={[
                                    { title: 'Month', dataIndex: 'period', key: 'period' },
                                    { title: 'Donations', dataIndex: 'donation_count', key: 'donation_count', align: 'right' },
                                    { title: 'Total', dataIndex: 'total_amount', key: 'total_amount', align: 'right', render: formatBdt },
                                ]}
                            />
                        ) : <Empty />}
                    </Card>
                </Col>
                <Col xs={24} md={12}>
                    <Card title="Yearly" size="small">
                        {d?.yearly?.length ? (
                            <Table
                                size="small"
                                dataSource={d.yearly}
                                rowKey="period"
                                pagination={false}
                                columns={[
                                    { title: 'Year', dataIndex: 'period', key: 'period' },
                                    { title: 'Donations', dataIndex: 'donation_count', key: 'donation_count', align: 'right' },
                                    { title: 'Total', dataIndex: 'total_amount', key: 'total_amount', align: 'right', render: formatBdt },
                                ]}
                            />
                        ) : <Empty />}
                    </Card>
                </Col>
            </Row>

            <Divider />

            <Card title={<><TeamOutlined /> Top Donor Contributions (Top 20)</>} size="small">
                {d?.top_donors?.length ? (
                    <Table
                        size="small"
                        dataSource={d.top_donors}
                        rowKey="id"
                        loading={isFetching}
                        pagination={{ pageSize: 10 }}
                        scroll={{ x: 'max-content' }}
                        columns={[
                            { title: '#', key: 'rank', render: (_, __, i) => i + 1, width: 50 },
                            { title: 'Donor ID', dataIndex: 'donor_id_code', key: 'donor_id_code', render: (c) => <Tag color="purple">{c}</Tag> },
                            { title: 'Name', dataIndex: 'name', key: 'name' },
                            { title: 'Phone', dataIndex: 'phone_number', key: 'phone_number' },
                            { title: '# Donations', dataIndex: 'donation_count', key: 'donation_count', align: 'right' },
                            { title: 'Total Contributed', dataIndex: 'total_contributed', key: 'total_contributed', align: 'right', render: (v) => <Text strong style={{ color: '#3f8600' }}>{formatBdt(v)}</Text> },
                            { title: 'Last Donation', dataIndex: 'last_donation_at', key: 'last_donation_at', render: (d) => d ? new Date(d).toLocaleDateString() : '—' },
                        ]}
                    />
                ) : <Empty description="No donations recorded yet" />}
            </Card>

            {d?.by_status?.length > 0 && (
                <>
                    <Divider />
                    <Card title="Status Breakdown" size="small">
                        <Row gutter={16}>
                            {d.by_status.map((s) => (
                                <Col key={s.status} xs={12} md={8}>
                                    <Card size="small" style={{ marginBottom: 8 }}>
                                        <Statistic
                                            title={<Tag color={s.status === 'confirmed' ? 'green' : s.status === 'failed' ? 'red' : 'gold'}>{s.status}</Tag>}
                                            value={s.total_amount}
                                            suffix="BDT"
                                        />
                                        <Text type="secondary">{s.donation_count} donations</Text>
                                    </Card>
                                </Col>
                            ))}
                        </Row>
                    </Card>
                </>
            )}
        </>
    );
}

/* ========================================================================== */
/* Tab 4: Cash Flow                                                            */
/* ========================================================================== */
function CashFlowTab() {
    const [dateRange, setDateRange] = useState(null);
    const params = dateRange ? {
        from: dateRange[0].format('YYYY-MM-DD'),
        to:   dateRange[1].format('YYYY-MM-DD'),
    } : {};
    const { data, isLoading, isFetching, error, refetch } = useGetCashFlowReportQuery(params);

    if (isLoading) return <Spin />;
    if (error) return <Alert message="Failed to load cash flow" type="error" showIcon />;

    const rows = data?.data?.rows || [];
    const totals = data?.data?.totals;

    return (
        <>
            <Row gutter={[16, 16]} style={{ marginBottom: 16 }}>
                <Col xs={12} md={6}><Card><Statistic title="Inflow" value={totals?.inflow || 0} suffix="BDT" valueStyle={{ color: '#3f8600' }} /></Card></Col>
                <Col xs={12} md={6}><Card><Statistic title="Outflow" value={totals?.outflow || 0} suffix="BDT" valueStyle={{ color: '#cf1322' }} /></Card></Col>
                <Col xs={12} md={6}><Card><Statistic title="Net" value={totals?.net || 0} suffix="BDT" valueStyle={{ color: (totals?.net || 0) < 0 ? '#cf1322' : '#3f8600' }} /></Card></Col>
                <Col xs={12} md={6}><Card><Statistic title="Closing Balance" value={totals?.closing_balance || 0} suffix="BDT" /></Card></Col>
            </Row>

            <Row gutter={[12, 12]} style={{ marginBottom: 16 }}>
                <Col xs={24} md={12}>
                    <RangePicker style={{ width: '100%' }} value={dateRange} onChange={setDateRange} />
                </Col>
                <Col flex="auto" style={{ textAlign: 'right' }}>
                    <Space>
                        <Button icon={<ReloadOutlined />} onClick={refetch}>Refresh</Button>
                        <Button
                            icon={<FileExcelOutlined />}
                            disabled={!rows.length}
                            onClick={() => downloadCsv(`cash-flow-${new Date().toISOString().slice(0,10)}.csv`, rows)}
                        >
                            Export CSV
                        </Button>
                    </Space>
                </Col>
            </Row>

            <Table
                dataSource={rows}
                rowKey="period"
                loading={isFetching}
                pagination={false}
                scroll={{ x: 'max-content' }}
                columns={[
                    { title: 'Month',     dataIndex: 'period',  key: 'period' },
                    { title: 'Inflow',    dataIndex: 'inflow',  key: 'inflow',  align: 'right', render: (v) => <Text style={{ color: '#3f8600' }}>{formatBdt(v)}</Text> },
                    { title: 'Outflow',   dataIndex: 'outflow', key: 'outflow', align: 'right', render: (v) => <Text style={{ color: '#cf1322' }}>{formatBdt(v)}</Text> },
                    { title: 'Net',       dataIndex: 'net',     key: 'net',     align: 'right', render: (v) => <Text strong style={{ color: v < 0 ? '#cf1322' : '#3f8600' }}>{formatBdt(v)}</Text> },
                    { title: 'Balance',   dataIndex: 'balance', key: 'balance', align: 'right', render: (v) => <Text style={{ color: v < 0 ? '#cf1322' : undefined }}>{formatBdt(v)}</Text> },
                ]}
            />
        </>
    );
}

/* ========================================================================== */
/* Tab 5: Donation Ledger                                                      */
/* ========================================================================== */
function DonationLedgerTab() {
    const [dateRange, setDateRange] = useState(null);
    const [donorId, setDonorId] = useState(null);
    const [projectId, setProjectId] = useState(null);

    const params = {};
    if (dateRange) { params.from = dateRange[0].format('YYYY-MM-DD'); params.to = dateRange[1].format('YYYY-MM-DD'); }
    if (donorId)   params.donor_id = donorId;
    if (projectId) params.project_id = projectId;

    const { data, isLoading, isFetching, error, refetch } = useGetDonationLedgerQuery(params);
    const { data: projectsData } = useGetProjectsQuery({});

    if (isLoading) return <Spin />;
    if (error) return <Alert message="Failed to load donation ledger" type="error" showIcon />;

    const rows = data?.data?.rows || [];
    const totals = data?.data?.totals;
    // Flatten for CSV
    const flatRows = rows.map((r) => ({
        date:     r.transaction_date,
        receipt:  r.receipt_number,
        donor:    r.donor?.name,
        donor_id: r.donor?.donor_id_code,
        project:  r.project?.name,
        student:  r.student,
        amount:   r.amount,
        method:   r.method,
        status:   r.status,
        balance:  r.balance,
    }));

    return (
        <>
            <Row gutter={[16, 16]} style={{ marginBottom: 16 }}>
                <Col xs={12} md={8}><Card><Statistic title="Donations" value={totals?.count || 0} /></Card></Col>
                <Col xs={12} md={8}><Card><Statistic title="Total Amount" value={totals?.amount || 0} suffix="BDT" valueStyle={{ color: '#3f8600' }} /></Card></Col>
                <Col xs={24} md={8}><Card><Statistic title="Closing Balance" value={totals?.closing_balance || 0} suffix="BDT" /></Card></Col>
            </Row>

            <Row gutter={[12, 12]} style={{ marginBottom: 16 }}>
                <Col xs={24} md={8}>
                    <RangePicker style={{ width: '100%' }} value={dateRange} onChange={setDateRange} />
                </Col>
                <Col xs={12} md={6}>
                    <Select
                        placeholder="Project"
                        allowClear
                        showSearch
                        optionFilterProp="label"
                        style={{ width: '100%' }}
                        value={projectId || undefined}
                        onChange={setProjectId}
                        options={(projectsData?.data || []).map((p) => ({ value: p.id, label: `${p.project_code} — ${p.name}` }))}
                    />
                </Col>
                <Col flex="auto" style={{ textAlign: 'right' }}>
                    <Space>
                        <Button icon={<ReloadOutlined />} onClick={refetch}>Refresh</Button>
                        <Button
                            icon={<FileExcelOutlined />}
                            disabled={!flatRows.length}
                            onClick={() => downloadCsv(`donation-ledger-${new Date().toISOString().slice(0,10)}.csv`, flatRows)}
                        >
                            Export CSV
                        </Button>
                    </Space>
                </Col>
            </Row>

            <Table
                dataSource={rows}
                rowKey="id"
                loading={isFetching}
                pagination={{ pageSize: 20 }}
                scroll={{ x: 'max-content' }}
                columns={[
                    { title: 'Date',    dataIndex: 'transaction_date', key: 'transaction_date', render: (d) => d ? new Date(d).toLocaleDateString() : '—' },
                    { title: 'Receipt', dataIndex: 'receipt_number',   key: 'receipt_number' },
                    { title: 'Donor',   key: 'donor',   render: (_, r) => r.donor ? `${r.donor.name} (${r.donor.donor_id_code})` : '—' },
                    { title: 'Project', key: 'project', render: (_, r) => r.project?.name || '—' },
                    { title: 'Student', key: 'student', render: (_, r) => r.student || '—' },
                    { title: 'Method',  dataIndex: 'method',  key: 'method' },
                    { title: 'Amount',  dataIndex: 'amount',  key: 'amount',  align: 'right', render: (v) => <Text strong style={{ color: '#3f8600' }}>{formatBdt(v)}</Text> },
                    { title: 'Balance', dataIndex: 'balance', key: 'balance', align: 'right', render: formatBdt },
                    { title: 'Status',  dataIndex: 'status',  key: 'status',
                        render: (s) => <Tag color={s === 'confirmed' ? 'green' : s === 'failed' ? 'red' : 'gold'}>{s}</Tag>,
                    },
                ]}
            />
        </>
    );
}

/* ========================================================================== */
/* Tab 6: Project Balance                                                      */
/* ========================================================================== */
function ProjectBalanceTab() {
    const { data, isLoading, isFetching, error, refetch } = useGetProjectBalanceReportQuery();

    if (isLoading) return <Spin />;
    if (error) return <Alert message="Failed to load project balance" type="error" showIcon />;

    const rows = data?.data?.rows || [];
    const totals = data?.data?.totals;

    return (
        <>
            <Row gutter={[16, 16]} style={{ marginBottom: 16 }}>
                <Col xs={12} md={5}><Card><Statistic title="Budget"    value={totals?.budget    || 0} suffix="BDT" /></Card></Col>
                <Col xs={12} md={5}><Card><Statistic title="Donations" value={totals?.donations || 0} suffix="BDT" valueStyle={{ color: '#3f8600' }} /></Card></Col>
                <Col xs={12} md={5}><Card><Statistic title="Expenses"  value={totals?.expenses  || 0} suffix="BDT" valueStyle={{ color: '#cf1322' }} /></Card></Col>
                <Col xs={12} md={5}><Card><Statistic title="Remaining" value={totals?.remaining || 0} suffix="BDT" /></Card></Col>
                <Col xs={24} md={4}><Card><Statistic title="Cash on Hand" value={totals?.cash_on_hand || 0} suffix="BDT" valueStyle={{ color: (totals?.cash_on_hand || 0) < 0 ? '#cf1322' : '#3f8600' }} /></Card></Col>
            </Row>

            <Space style={{ marginBottom: 16 }}>
                <Button icon={<ReloadOutlined />} onClick={refetch}>Refresh</Button>
                <Button icon={<FileExcelOutlined />} disabled={!rows.length} onClick={() => downloadCsv(`project-balance-${new Date().toISOString().slice(0,10)}.csv`, rows)}>
                    Export CSV
                </Button>
            </Space>

            <Table
                dataSource={rows}
                rowKey="id"
                loading={isFetching}
                pagination={false}
                scroll={{ x: 'max-content' }}
                columns={[
                    { title: 'Code', dataIndex: 'project_code', key: 'project_code' },
                    { title: 'Project', dataIndex: 'name', key: 'name' },
                    { title: 'Type', dataIndex: 'type', key: 'type', render: (t) => t || '—' },
                    { title: 'Status', dataIndex: 'status', key: 'status', render: (s) => <Tag color={STATUS_COLORS[s] || 'default'}>{s}</Tag> },
                    { title: 'Budget', dataIndex: 'budget', key: 'budget', align: 'right', render: formatBdt },
                    { title: 'Donations', dataIndex: 'donations', key: 'donations', align: 'right', render: (v) => <Text style={{ color: '#3f8600' }}>{formatBdt(v)}</Text> },
                    { title: 'Expenses', dataIndex: 'expenses', key: 'expenses', align: 'right', render: (v) => <Text style={{ color: '#cf1322' }}>{formatBdt(v)}</Text> },
                    { title: 'Remaining', dataIndex: 'remaining', key: 'remaining', align: 'right', render: (v) => <Text style={{ color: v < 0 ? '#cf1322' : undefined }}>{formatBdt(v)}</Text> },
                    { title: 'Cash on Hand', dataIndex: 'cash_on_hand', key: 'cash_on_hand', align: 'right', render: (v) => <Text strong style={{ color: v < 0 ? '#cf1322' : '#3f8600' }}>{formatBdt(v)}</Text> },
                    { title: 'Utilization', dataIndex: 'utilization', key: 'utilization', width: 130, render: (p) => <Progress percent={Math.min(p, 100)} size="small" status={p > 100 ? 'exception' : 'normal'} strokeColor={p > 100 ? '#cf1322' : '#fa8c16'} format={() => `${p}%`} /> },
                ]}
            />
        </>
    );
}

/* ========================================================================== */
/* Tab 7: Financial Reconciliation                                             */
/* ========================================================================== */
function FinancialReconciliationTab() {
    const { data, isLoading, isFetching, error, refetch } = useGetFinancialReconciliationReportQuery();

    if (isLoading) return <Spin />;
    if (error) return <Alert message="Failed to load reconciliation report" type="error" showIcon />;

    const s = data?.data?.summary;
    const uploads = data?.data?.uploads || [];
    const total = (s?.reconciled?.count || 0) + (s?.unreconciled?.count || 0) + (s?.duplicate?.count || 0);

    return (
        <>
            <Row gutter={[16, 16]} style={{ marginBottom: 16 }}>
                <Col xs={12} md={6}>
                    <Card><Statistic title="Reconciled" value={s?.reconciled?.count || 0} suffix={`/ ${total}`} valueStyle={{ color: '#3f8600' }} /></Card>
                </Col>
                <Col xs={12} md={6}>
                    <Card><Statistic title="Reconciled amount" value={s?.reconciled?.amount || 0} suffix="BDT" valueStyle={{ color: '#3f8600' }} /></Card>
                </Col>
                <Col xs={12} md={6}>
                    <Card><Statistic title="Unreconciled" value={s?.unreconciled?.count || 0} valueStyle={{ color: '#cf1322' }} /></Card>
                </Col>
                <Col xs={12} md={6}>
                    <Card><Statistic title="Unreconciled amount" value={s?.unreconciled?.amount || 0} suffix="BDT" valueStyle={{ color: '#cf1322' }} /></Card>
                </Col>
            </Row>

            <Row gutter={[12, 12]} style={{ marginBottom: 16 }}>
                {['matched', 'donor_created', 'unmatched', 'duplicate', 'error', 'skipped'].map((k) => (
                    <Col key={k} xs={12} md={4}>
                        <Card size="small">
                            <Text type="secondary" style={{ textTransform: 'capitalize' }}>{k.replace('_', ' ')}</Text>
                            <div style={{ fontSize: 18, fontWeight: 600 }}>{s?.[k]?.count || 0}</div>
                            <Text type="secondary" style={{ fontSize: 12 }}>{formatBdt(s?.[k]?.amount || 0)}</Text>
                        </Card>
                    </Col>
                ))}
            </Row>

            <Space style={{ marginBottom: 16 }}>
                <Button icon={<ReloadOutlined />} onClick={refetch}>Refresh</Button>
                <Button icon={<FileExcelOutlined />} disabled={!uploads.length} onClick={() => downloadCsv(`reconciliation-summary-${new Date().toISOString().slice(0,10)}.csv`, uploads)}>
                    Export CSV
                </Button>
            </Space>

            <Table
                dataSource={uploads}
                rowKey="id"
                loading={isFetching}
                pagination={{ pageSize: 15 }}
                scroll={{ x: 'max-content' }}
                columns={[
                    { title: 'Upload', dataIndex: 'original_name', key: 'original_name' },
                    { title: 'Date', dataIndex: 'created_at', key: 'created_at', render: (d) => d ? new Date(d).toLocaleDateString() : '—' },
                    { title: 'Total rows', dataIndex: 'total_rows', key: 'total_rows', align: 'right' },
                    { title: 'Matched', dataIndex: 'matched_rows', key: 'matched_rows', align: 'right', render: (v) => <Text style={{ color: '#3f8600' }}>{v}</Text> },
                    { title: 'Unmatched', dataIndex: 'unmatched_rows', key: 'unmatched_rows', align: 'right', render: (v) => <Text style={{ color: '#cf1322' }}>{v}</Text> },
                    { title: 'Duplicate', dataIndex: 'duplicate_rows', key: 'duplicate_rows', align: 'right' },
                    { title: 'Error', dataIndex: 'error_rows', key: 'error_rows', align: 'right' },
                    { title: 'Amount', dataIndex: 'total_amount', key: 'total_amount', align: 'right', render: formatBdt },
                    {
                        title: 'Reconcile %',
                        dataIndex: 'reconcile_rate',
                        key: 'reconcile_rate',
                        width: 150,
                        render: (p) => <Progress percent={Math.min(p, 100)} size="small" status={p >= 95 ? 'success' : 'active'} format={() => `${p}%`} />,
                    },
                ]}
            />
        </>
    );
}

/* ========================================================================== */
/* Main ReportsPage                                                            */
/* ========================================================================== */
export default function ReportsPage() {
    const [activeTab, setActiveTab] = useState('project-wise');
    const [detailProjectId, setDetailProjectId] = useState(null);

    const handleOpenDetail = (id) => {
        setDetailProjectId(id);
        setActiveTab('project-detail');
    };

    return (
        <Card title="Reports">
            <Tabs
                activeKey={activeTab}
                onChange={setActiveTab}
                items={[
                    {
                        key: 'project-wise',
                        label: <span><ProjectOutlined /> Project-wise</span>,
                        children: <ProjectWiseTab onOpenDetail={handleOpenDetail} />,
                    },
                    {
                        key: 'project-detail',
                        label: <span><FileExcelOutlined /> Project Detail</span>,
                        children: (
                            <ProjectDetailTab
                                projectId={detailProjectId}
                                onProjectChange={setDetailProjectId}
                            />
                        ),
                    },
                    {
                        key: 'donation-summary',
                        label: <span><LineChartOutlined /> Donation Summary</span>,
                        children: <DonationSummaryTab />,
                    },
                    {
                        key: 'cash-flow',
                        label: <span><BankOutlined /> Cash Flow</span>,
                        children: <CashFlowTab />,
                    },
                    {
                        key: 'donation-ledger',
                        label: <span><AccountBookOutlined /> Donation Ledger</span>,
                        children: <DonationLedgerTab />,
                    },
                    {
                        key: 'project-balance',
                        label: <span><PieChartOutlined /> Project Balance</span>,
                        children: <ProjectBalanceTab />,
                    },
                    {
                        key: 'financial-reconciliation',
                        label: <span><SwapOutlined /> Financial Reconciliation</span>,
                        children: <FinancialReconciliationTab />,
                    },
                ]}
            />
        </Card>
    );
}
