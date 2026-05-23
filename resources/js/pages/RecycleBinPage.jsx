import React, { useState, useMemo } from 'react';
import {
    Card, Table, Tag, Button, Space, Popconfirm, message, Spin, Empty,
    Row, Col, Statistic, Select, Typography,
} from 'antd';
import {
    UndoOutlined, DeleteOutlined, WarningOutlined, ReloadOutlined,
    DatabaseOutlined, CloudDownloadOutlined,
} from '@ant-design/icons';
import {
    useGetRecycleBinQuery,
    useRestoreRecycleBinItemMutation,
    useForceDeleteRecycleBinItemMutation,
    useEmptyRecycleBinMutation,
    useLazyGetBackupUrlQuery,
} from '../store/apiSlice.js';
import { usePermissions } from '../utils/permissions.js';

const { Text } = Typography;

const TYPE_COLORS = {
    donors: 'purple', projects: 'blue', 'project-types': 'cyan',
    donations: 'green', students: 'gold', expenses: 'red',
};

export default function RecycleBinPage() {
    const { can } = usePermissions();
    const { data, isLoading, isFetching, refetch } = useGetRecycleBinQuery();
    const [restore, { isLoading: isRestoring }] = useRestoreRecycleBinItemMutation();
    const [forceDelete, { isLoading: isForceDeleting }] = useForceDeleteRecycleBinItemMutation();
    const [emptyBin, { isLoading: isEmptying }] = useEmptyRecycleBinMutation();
    const [fetchBackupUrl, { isFetching: isFetchingBackup }] = useLazyGetBackupUrlQuery();

    const [typeFilter, setTypeFilter] = useState('');

    const items = data?.data?.items || [];
    const counts = data?.data?.counts || {};
    const types  = data?.data?.types || [];
    const total  = items.length;

    const filtered = useMemo(
        () => typeFilter ? items.filter((i) => i.type === typeFilter) : items,
        [items, typeFilter],
    );

    const handleRestore = async (record) => {
        try {
            await restore({ type: record.type, id: record.id }).unwrap();
            message.success('Restored');
        } catch (e) { message.error(e?.data?.message || 'Restore failed'); }
    };

    const handleForceDelete = async (record) => {
        try {
            await forceDelete({ type: record.type, id: record.id }).unwrap();
            message.success('Permanently deleted');
        } catch (e) { message.error(e?.data?.message || 'Delete failed'); }
    };

    const handleEmpty = async () => {
        try {
            const res = await emptyBin().unwrap();
            message.success(res.message || 'Bin emptied');
        } catch (e) { message.error(e?.data?.message || 'Failed'); }
    };

    const handleBackup = async () => {
        try {
            const res = await fetchBackupUrl().unwrap();
            if (res?.data?.url) {
                window.open(res.data.url, '_blank');
            }
        } catch (e) {
            message.error(e?.data?.message || 'Backup unavailable');
        }
    };

    if (isLoading) return <div style={{ textAlign: 'center', marginTop: 50 }}><Spin size="large" /></div>;

    return (
        <Card title={<><DeleteOutlined /> Recycle Bin & Backup</>}>
            <Row gutter={[12, 12]} style={{ marginBottom: 16 }}>
                {types.map((t) => (
                    <Col key={t} xs={12} sm={8} md={4}>
                        <Card size="small">
                            <Statistic
                                title={<Tag color={TYPE_COLORS[t] || 'default'}>{t}</Tag>}
                                value={counts[t] || 0}
                            />
                        </Card>
                    </Col>
                ))}
            </Row>

            <Space style={{ marginBottom: 16 }} wrap>
                <Select
                    placeholder="Filter by type"
                    allowClear
                    style={{ minWidth: 200 }}
                    value={typeFilter || undefined}
                    onChange={(v) => setTypeFilter(v || '')}
                    options={types.map((t) => ({ value: t, label: t }))}
                />
                <Button icon={<ReloadOutlined />} onClick={refetch}>Refresh</Button>
                {can('recycle-bin.force_delete') && total > 0 && (
                    <Popconfirm
                        title="Empty the entire recycle bin?"
                        description="All trashed records (across every type) will be permanently destroyed."
                        okText="Empty it"
                        okButtonProps={{ danger: true }}
                        onConfirm={handleEmpty}
                    >
                        <Button danger icon={<WarningOutlined />} loading={isEmptying}>
                            Empty Bin ({total})
                        </Button>
                    </Popconfirm>
                )}
                {can('backup.create') && (
                    <Button
                        icon={<CloudDownloadOutlined />}
                        onClick={handleBackup}
                        loading={isFetchingBackup}
                    >
                        Download DB Backup
                    </Button>
                )}
            </Space>

            {total === 0 ? <Empty description="Recycle bin is empty" /> : (
                <Table
                    dataSource={filtered}
                    rowKey={(r) => `${r.type}-${r.id}`}
                    loading={isFetching}
                    pagination={{ pageSize: 20 }}
                    scroll={{ x: 'max-content' }}
                    columns={[
                        {
                            title: 'Type',
                            dataIndex: 'type',
                            key: 'type',
                            render: (t) => <Tag color={TYPE_COLORS[t] || 'default'}>{t}</Tag>,
                        },
                        { title: 'ID', dataIndex: 'id', key: 'id', width: 80 },
                        { title: 'Label', dataIndex: 'label', key: 'label' },
                        {
                            title: 'Deleted at',
                            dataIndex: 'deleted_at',
                            key: 'deleted_at',
                            render: (d) => d ? new Date(d).toLocaleString() : '—',
                        },
                        {
                            title: 'Actions',
                            key: 'actions',
                            width: 240,
                            render: (_, record) => (
                                <Space>
                                    {can('recycle-bin.restore') && (
                                        <Button
                                            size="small"
                                            type="primary"
                                            icon={<UndoOutlined />}
                                            loading={isRestoring}
                                            onClick={() => handleRestore(record)}
                                        >
                                            Restore
                                        </Button>
                                    )}
                                    {can('recycle-bin.force_delete') && (
                                        <Popconfirm
                                            title="Delete permanently?"
                                            description="This row cannot be recovered."
                                            okText="Delete forever"
                                            okButtonProps={{ danger: true }}
                                            onConfirm={() => handleForceDelete(record)}
                                        >
                                            <Button size="small" danger icon={<DeleteOutlined />} loading={isForceDeleting}>
                                                Force Delete
                                            </Button>
                                        </Popconfirm>
                                    )}
                                </Space>
                            ),
                        },
                    ]}
                />
            )}
        </Card>
    );
}
