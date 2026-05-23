import React from 'react';
import { Table, Tag, Card, Alert } from 'antd';
import dayjs from 'dayjs';
import { useGetActivityLogsQuery } from '../store/apiSlice.js';

const eventColor = (e) => {
    switch (e) {
        case 'created': return 'green';
        case 'updated': return 'gold';
        case 'deleted': return 'red';
        default:        return 'default';
    }
};

export default function AuditLogTable() {
    const { data, isFetching, error } = useGetActivityLogsQuery({});

    const rows = data?.data?.data || data?.data || [];

    const columns = [
        {
            title: 'When',
            dataIndex: 'created_at',
            key: 'created_at',
            width: 170,
            render: (d) => d ? dayjs(d).format('YYYY-MM-DD HH:mm:ss') : '—',
        },
        {
            title: 'Event',
            dataIndex: 'event',
            key: 'event',
            width: 110,
            render: (e) => e ? <Tag color={eventColor(e)}>{e}</Tag> : '—',
        },
        {
            title: 'Subject',
            key: 'subject',
            render: (_, r) => r.subject_type
                ? <span>{r.subject_type} <Tag>#{r.subject_id}</Tag></span>
                : '—',
        },
        {
            title: 'By',
            key: 'causer',
            render: (_, r) => r.causer?.name || 'System',
        },
        {
            title: 'Description',
            dataIndex: 'description',
            key: 'description',
        },
    ];

    return (
        <Card title="Activity Logs">
            {error && (
                <Alert
                    type="error"
                    showIcon
                    message="Failed to load activity logs"
                    description={error?.data?.message || `HTTP ${error?.status}`}
                    style={{ marginBottom: 12 }}
                />
            )}
            <Table
                columns={columns}
                dataSource={rows}
                loading={isFetching}
                rowKey="id"
                scroll={{ x: 'max-content' }}
                pagination={data?.data?.current_page ? {
                    current: data.data.current_page,
                    pageSize: data.data.per_page,
                    total: data.data.total,
                } : { pageSize: 25 }}
            />
        </Card>
    );
}
