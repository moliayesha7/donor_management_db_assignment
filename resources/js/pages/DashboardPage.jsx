import React from 'react';
import { Card, Row, Col, Statistic, Table, Tag, Progress, Spin, Typography, Space } from 'antd';
import {
    DollarOutlined, WalletOutlined, ProjectOutlined, TeamOutlined,
    WarningOutlined, BankOutlined, ReadOutlined, FileTextOutlined,
} from '@ant-design/icons';
import {
    useGetProjectWiseReportQuery,
    useGetDonationSummaryQuery,
    useGetCashFlowReportQuery,
    useGetReconciliationUnmatchedQuery,
    useGetDonorsQuery,
    useGetStudentsQuery,
} from '../store/apiSlice.js';
import { usePermissions } from '../utils/permissions.js';

const { Text } = Typography;

const STATUS_COLORS = { pending: 'gold', active: 'green', completed: 'blue', suspended: 'red' };
const formatBdt = (n) => `${Number(n || 0).toLocaleString()} POUND`;

export default function DashboardPage() {
    const { user, can } = usePermissions();
    const { data: projectWise,    isLoading: l1 } = useGetProjectWiseReportQuery({});
    const { data: donationSummary, isLoading: l2 } = useGetDonationSummaryQuery({});
    const { data: cashFlow,       isLoading: l3 } = useGetCashFlowReportQuery({}, { skip: !can('reports.view') });
    const { data: unmatched }      = useGetReconciliationUnmatchedQuery(undefined, { skip: !can('reconciliation.view') });
    const { data: donors }         = useGetDonorsQuery({}, { skip: !can('donors.view') });
    const { data: students }       = useGetStudentsQuery({}, { skip: !can('students.view') });

    if (l1 || l2) return <div style={{ textAlign: 'center', marginTop: 50 }}><Spin size="large" /></div>;

    const pwTotals = projectWise?.data?.totals;
    const dsTotals = donationSummary?.data?.totals;
    const cfTotals = cashFlow?.data?.totals;

    const topProjects = (projectWise?.data?.projects || [])
        .slice()
        .sort((a, b) => (b.funded_percent || 0) - (a.funded_percent || 0))
        .slice(0, 5);

    const topDonors = donationSummary?.data?.top_donors?.slice(0, 5) || [];

    return (
        <Space direction="vertical" size={16} style={{ width: '100%' }}>
            <Card size="small" style={{ background: '#fafafa' }}>
                <Text strong style={{ fontSize: 18 }}>Welcome back, {user?.name || 'there'}!</Text>
                <div><Text type="secondary">Here's a snapshot of the system.</Text></div>
            </Card>

            <Row gutter={[16, 16]}>
                <Col xs={12} md={6}>
                    <Card><Statistic title="Total Donations" value={dsTotals?.donations_total || 0} suffix="POUND" prefix={<DollarOutlined />} valueStyle={{ color: '#3f8600' }} /></Card>
                </Col>
                <Col xs={12} md={6}>
                    <Card><Statistic title="Total Expenses" value={pwTotals?.expenses_total || 0} suffix="POUND" prefix={<WalletOutlined />} valueStyle={{ color: '#cf1322' }} /></Card>
                </Col>
                <Col xs={12} md={6}>
                    <Card><Statistic title="Cash on Hand" value={cfTotals?.closing_balance ?? ((dsTotals?.donations_total || 0) - (pwTotals?.expenses_total || 0))} suffix="POUND" prefix={<BankOutlined />} valueStyle={{ color: (cfTotals?.closing_balance || 0) < 0 ? '#cf1322' : '#3f8600' }} /></Card>
                </Col>
                <Col xs={12} md={6}>
                    <Card><Statistic title="Budget Allocated" value={pwTotals?.budget_total || 0} suffix="POUND" prefix={<ProjectOutlined />} /></Card>
                </Col>
            </Row>

            <Row gutter={[16, 16]}>
                <Col xs={12} md={6}>
                    <Card><Statistic title="Projects" value={pwTotals?.project_count || 0} prefix={<ProjectOutlined />} /></Card>
                </Col>
                <Col xs={12} md={6}>
                    <Card><Statistic title="Donors" value={donors?.data?.length || 0} prefix={<TeamOutlined />} /></Card>
                </Col>
                <Col xs={12} md={6}>
                    <Card><Statistic title="Students" value={students?.data?.length || students?.data?.data?.length || 0} prefix={<ReadOutlined />} /></Card>
                </Col>
                <Col xs={12} md={6}>
                    <Card><Statistic title="Unmatched Txns" value={unmatched?.data?.totals?.count || 0} prefix={<WarningOutlined />} valueStyle={{ color: (unmatched?.data?.totals?.count || 0) > 0 ? '#cf1322' : undefined }} /></Card>
                </Col>
            </Row>

            <Row gutter={[16, 16]}>
                <Col xs={24} lg={14}>
                    <Card title={<><ProjectOutlined /> Top Projects (by funded %)</>}>
                        <Table
                            size="small"
                            dataSource={topProjects}
                            rowKey="id"
                            pagination={false}
                            scroll={{ x: 'max-content' }}
                            columns={[
                                { title: 'Project', dataIndex: 'name', key: 'name' },
                                { title: 'Status', dataIndex: 'status', key: 'status', render: (s) => <Tag color={STATUS_COLORS[s] || 'default'}>{s}</Tag> },
                                { title: 'Raised',    dataIndex: 'donations_total', key: 'donations_total', align: 'right', render: (v) => <Text style={{ color: '#3f8600' }}>{formatBdt(v)}</Text> },
                                { title: 'Spent',     dataIndex: 'expenses_total',  key: 'expenses_total',  align: 'right', render: (v) => <Text style={{ color: '#cf1322' }}>{formatBdt(v)}</Text> },
                                { title: 'Funded', dataIndex: 'funded_percent', key: 'funded_percent', width: 130,
                                    render: (p) => <Progress percent={Math.min(p, 100)} size="small" format={() => `${p}%`} />,
                                },
                            ]}
                        />
                    </Card>
                </Col>
                <Col xs={24} lg={10}>
                    <Card title={<><TeamOutlined /> Top Donors</>}>
                        <Table
                            size="small"
                            dataSource={topDonors}
                            rowKey="id"
                            pagination={false}
                            columns={[
                                { title: '#', key: 'rank', width: 40, render: (_, __, i) => i + 1 },
                                { title: 'Donor', key: 'name', render: (_, r) => (
                                    <div style={{ lineHeight: 1.3 }}>
                                        <div>{r.name}</div>
                                        <Tag color="purple" style={{ fontSize: 11 }}>{r.donor_id_code}</Tag>
                                    </div>
                                ) },
                                { title: 'Contributed', dataIndex: 'total_contributed', key: 'total_contributed', align: 'right',
                                    render: (v) => <Text strong style={{ color: '#3f8600' }}>{formatBdt(v)}</Text>,
                                },
                            ]}
                        />
                    </Card>
                </Col>
            </Row>

            <Card title={<><FileTextOutlined /> Recent Monthly Donations</>}>
                <Table
                    size="small"
                    dataSource={donationSummary?.data?.monthly || []}
                    rowKey="period"
                    pagination={false}
                    columns={[
                        { title: 'Month', dataIndex: 'period', key: 'period' },
                        { title: 'Donations', dataIndex: 'donation_count', key: 'donation_count', align: 'right' },
                        { title: 'Amount',    dataIndex: 'total_amount',   key: 'total_amount', align: 'right', render: formatBdt },
                    ]}
                />
            </Card>
        </Space>
    );
}
