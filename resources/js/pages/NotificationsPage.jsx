import React, { useState } from 'react';
import {
    Card, Tabs, Table, Tag, Input, Button, Space, message, Row, Col, Statistic,
} from 'antd';
import {
    MailOutlined, MessageOutlined, WhatsAppOutlined, SearchOutlined,
    RedoOutlined, ReloadOutlined,
} from '@ant-design/icons';
import {
    useGetEmailLogsQuery,
    useGetSmsLogsQuery,
    useGetWhatsappLogsQuery,
    useRetryEmailLogMutation,
    useRetrySmsLogMutation,
    useRetryWhatsappLogMutation,
} from '../store/apiSlice.js';
import { usePermissions } from '../utils/permissions.js';

const STATUS_COLORS = {
    sent: 'green', delivered: 'green', queued: 'blue',
    pending: 'gold', failed: 'red', read: 'cyan',
};

function statusTag(s) {
    return <Tag color={STATUS_COLORS[s] || 'default'}>{s || '—'}</Tag>;
}

function ChannelTab({ channel, useLogsQuery, useRetryMutation, contactKey, contentKey, contentLabel }) {
    const { can } = usePermissions();
    const [search, setSearch] = useState('');
    const [page, setPage] = useState(1);
    const { data, isFetching, refetch } = useLogsQuery({ search, page, per_page: 15 });
    const [retry, { isLoading: isRetrying }] = useRetryMutation();

    // Both pagination shapes show up — Laravel paginate returns { data: [...], total, current_page } either directly under .data or nested.
    const payload = data?.data;
    const rows = Array.isArray(payload?.data) ? payload.data : (Array.isArray(payload) ? payload : []);
    const total = payload?.total ?? rows.length;

    const handleRetry = async (id) => {
        try {
            await retry(id).unwrap();
            message.success('Retry queued');
        } catch (e) {
            message.error(e?.data?.message || 'Retry failed');
        }
    };

    const failedCount = rows.filter((r) => r.status === 'failed').length;
    const sentCount   = rows.filter((r) => r.status === 'sent' || r.status === 'delivered').length;

    return (
        <>
            <Row gutter={[16, 16]} style={{ marginBottom: 16 }}>
                <Col xs={12} md={6}><Card><Statistic title={`${channel} total`}  value={total} /></Card></Col>
                <Col xs={12} md={6}><Card><Statistic title="Sent / Delivered" value={sentCount} valueStyle={{ color: '#3f8600' }} /></Card></Col>
                <Col xs={12} md={6}><Card><Statistic title="Failed (page)"   value={failedCount} valueStyle={{ color: '#cf1322' }} /></Card></Col>
            </Row>

            <Space style={{ marginBottom: 16 }} wrap>
                <Input
                    prefix={<SearchOutlined />}
                    placeholder={`Search by recipient or ${contentLabel.toLowerCase()}`}
                    allowClear
                    value={search}
                    onChange={(e) => { setSearch(e.target.value); setPage(1); }}
                    style={{ width: 320 }}
                />
                <Button icon={<ReloadOutlined />} onClick={refetch}>Refresh</Button>
            </Space>

            <Table
                dataSource={rows}
                rowKey="id"
                loading={isFetching}
                scroll={{ x: 'max-content' }}
                pagination={{
                    current: page,
                    total,
                    pageSize: 15,
                    onChange: setPage,
                }}
                columns={[
                    { title: 'Date',    dataIndex: 'date',    key: 'date',    render: (d, r) => d || (r.created_at ? new Date(r.created_at).toLocaleDateString() : '—') },
                    { title: 'Time',    dataIndex: 'time',    key: 'time',    render: (t, r) => t || (r.created_at ? new Date(r.created_at).toLocaleTimeString() : '—') },
                    { title: 'Recipient', key: 'recipient',
                        render: (_, r) => (
                            <div style={{ lineHeight: 1.3 }}>
                                <div>{r.recipient_name || '—'}</div>
                                <div style={{ fontSize: 12, color: '#888' }}>{r[contactKey] || '—'}</div>
                            </div>
                        ),
                    },
                    {
                        title: contentLabel,
                        dataIndex: contentKey,
                        key: contentKey,
                        ellipsis: true,
                        render: (v) => v || '—',
                    },
                    { title: 'Status',     dataIndex: 'status',     key: 'status', render: statusTag },
                    { title: 'Attempts',   dataIndex: 'attempts',   key: 'attempts', align: 'right', render: (n) => n ?? 0 },
                    { title: 'Provider',   dataIndex: 'provider_id', key: 'provider_id', ellipsis: true, render: (v) => v || '—' },
                    { title: 'Error',      dataIndex: 'error_message', key: 'error_message', ellipsis: true, render: (v) => v || '—' },
                    ...(can('notifications.send') ? [{
                        title: 'Actions',
                        key: 'actions',
                        width: 110,
                        render: (_, r) => r.status === 'failed' ? (
                            <Button size="small" icon={<RedoOutlined />} loading={isRetrying} onClick={() => handleRetry(r.id)}>Retry</Button>
                        ) : null,
                    }] : []),
                ]}
            />
        </>
    );
}

export default function NotificationsPage() {
    return (
        <Card title="Notifications">
            <Tabs
                items={[
                    {
                        key: 'email',
                        label: <span><MailOutlined /> Email</span>,
                        children: (
                            <ChannelTab
                                channel="Email"
                                useLogsQuery={useGetEmailLogsQuery}
                                useRetryMutation={useRetryEmailLogMutation}
                                contactKey="recipient_email"
                                contentKey="subject"
                                contentLabel="Subject"
                            />
                        ),
                    },
                    {
                        key: 'sms',
                        label: <span><MessageOutlined /> SMS</span>,
                        children: (
                            <ChannelTab
                                channel="SMS"
                                useLogsQuery={useGetSmsLogsQuery}
                                useRetryMutation={useRetrySmsLogMutation}
                                contactKey="recipient_number"
                                contentKey="text"
                                contentLabel="Message"
                            />
                        ),
                    },
                    {
                        key: 'whatsapp',
                        label: <span><WhatsAppOutlined /> WhatsApp</span>,
                        children: (
                            <ChannelTab
                                channel="WhatsApp"
                                useLogsQuery={useGetWhatsappLogsQuery}
                                useRetryMutation={useRetryWhatsappLogMutation}
                                contactKey="recipient_number"
                                contentKey="text"
                                contentLabel="Message"
                            />
                        ),
                    },
                ]}
            />
        </Card>
    );
}
