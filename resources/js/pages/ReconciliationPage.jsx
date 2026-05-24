import React, { useState } from 'react';
import { useSelector } from 'react-redux';
import {
    Card, Tabs, Table, Tag, Spin, Alert, Modal, Form, Select, Upload, Button,
    message, Space, Popconfirm, Row, Col, Statistic, Switch, Empty, Progress,
    Typography, Drawer, Descriptions, Tooltip,
} from 'antd';
import {
    UploadOutlined, DownloadOutlined, FileExcelOutlined, LinkOutlined,
    DeleteOutlined, EyeOutlined, ReloadOutlined, BankOutlined,
    CheckCircleOutlined, CloseCircleOutlined, WarningOutlined,
} from '@ant-design/icons';
import {
    useGetReconciliationUploadsQuery,
    useGetReconciliationUnmatchedQuery,
    useLazyGetReconciliationUploadQuery,
    useUploadReconciliationMutation,
    useDeleteReconciliationUploadMutation,
    useMatchReconciliationTransactionMutation,
    useGetProjectsQuery,
    useGetDonorsQuery,
} from '../store/apiSlice.js';
import { usePermissions } from '../utils/permissions.js';

const { Text } = Typography;

const MATCH_COLORS = {
    matched: 'green',
    donor_created: 'blue',
    unmatched: 'orange',
    duplicate: 'purple',
    skipped: 'default',
    error: 'red',
};

const formatBdt = (n) => `${Number(n || 0).toLocaleString()} POUND`;

/**
 * Hits the authenticated /reconciliation/template endpoint with the bearer token
 * (RTK Query can't easily return Blob with the rest of the setup) and triggers
 * a browser download.
 */
async function downloadTemplate(token) {
    try {
        const res = await fetch('/api/reconciliation/template', {
            headers: {
                Authorization: token ? `Bearer ${token}` : '',
                Accept: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            },
        });
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        const blob = await res.blob();
        const url  = URL.createObjectURL(blob);
        const a    = document.createElement('a');
        a.href = url;
        a.download = 'Template_external_donation_data.xlsx';
        a.click();
        URL.revokeObjectURL(url);
    } catch (e) {
        message.error('Failed to download template: ' + e.message);
    }
}

/* ========================================================================== */
/* Uploads tab                                                                 */
/* ========================================================================== */
function UploadsTab({ onOpenDetail }) {
    const token = useSelector((s) => s?.auth?.token);
    const { can } = usePermissions();
    const { data, isLoading, isFetching, refetch } = useGetReconciliationUploadsQuery();
    const { data: projectsData } = useGetProjectsQuery({});
    const [uploadFile, { isLoading: isUploading }] = useUploadReconciliationMutation();
    const [deleteUpload] = useDeleteReconciliationUploadMutation();

    const [isUploadOpen, setIsUploadOpen] = useState(false);
    const [fileList, setFileList] = useState([]);
    const [defaultProject, setDefaultProject] = useState(null);
    const [autoCreate, setAutoCreate] = useState(false);

    if (isLoading) return <Spin />;

    const uploads = data?.data?.uploads || [];
    const totals  = data?.data?.totals;

    const handleUpload = async () => {
        if (!fileList.length) {
            message.warning('Please select a file first.');
            return;
        }
        const fd = new FormData();
        fd.append('file', fileList[0].originFileObj);
        if (defaultProject) fd.append('default_project_id', defaultProject);
        fd.append('auto_create_donors', autoCreate ? 1 : 0);

        try {
            const result = await uploadFile(fd).unwrap();
            message.success(result.message);
            setIsUploadOpen(false);
            setFileList([]);
            setDefaultProject(null);
            setAutoCreate(false);
        } catch (err) {
            const errors = err?.data?.errors;
            if (errors) {
                const k = Object.keys(errors)[0];
                message.error(errors[k][0]);
            } else {
                message.error(err?.data?.message || 'Upload failed');
            }
        }
    };

    const handleDelete = async (id) => {
        try {
            await deleteUpload(id).unwrap();
            message.success('Upload deleted');
        } catch (err) {
            message.error(err?.data?.message || 'Failed to delete');
        }
    };

    return (
        <>
            <Row gutter={[16, 16]} style={{ marginBottom: 16 }}>
                <Col xs={12} md={6}>
                    <Card><Statistic title="Uploads" value={totals?.uploads || 0} prefix={<BankOutlined />} /></Card>
                </Col>
                <Col xs={12} md={6}>
                    <Card><Statistic title="Total Rows" value={totals?.total_rows || 0} /></Card>
                </Col>
                <Col xs={12} md={6}>
                    <Card><Statistic title="Matched" value={totals?.matched_rows || 0} prefix={<CheckCircleOutlined />} valueStyle={{ color: '#3f8600' }} /></Card>
                </Col>
                <Col xs={12} md={6}>
                    <Card><Statistic title="Unmatched" value={totals?.unmatched_rows || 0} prefix={<CloseCircleOutlined />} valueStyle={{ color: '#cf1322' }} /></Card>
                </Col>
            </Row>

            <Space style={{ marginBottom: 16 }} wrap>
                {can('reconciliation.upload') && (
                    <Button type="primary" icon={<UploadOutlined />} onClick={() => setIsUploadOpen(true)}>
                        Upload Statement
                    </Button>
                )}
                <Button icon={<DownloadOutlined />} onClick={() => downloadTemplate(token)}>
                    Download Template
                </Button>
                <Button icon={<ReloadOutlined />} onClick={refetch}>Refresh</Button>
            </Space>

            <Table
                dataSource={uploads}
                rowKey="id"
                loading={isFetching}
                scroll={{ x: 'max-content' }}
                columns={[
                    {
                        title: 'File',
                        dataIndex: 'original_name',
                        key: 'original_name',
                        render: (n) => <Space><FileExcelOutlined /> {n}</Space>,
                    },
                    {
                        title: 'Default Project',
                        key: 'default_project',
                        render: (_, r) => r.default_project?.name || <Text type="secondary">—</Text>,
                    },
                    { title: 'Total', dataIndex: 'total_rows', key: 'total_rows', align: 'right' },
                    {
                        title: 'Matched',
                        dataIndex: 'matched_rows',
                        key: 'matched_rows',
                        align: 'right',
                        render: (n, r) => {
                            const pct = r.total_rows > 0 ? Math.round((n / r.total_rows) * 100) : 0;
                            return (
                                <Tooltip title={`${pct}% reconciled`}>
                                    <Text style={{ color: '#3f8600' }}>{n}</Text>
                                </Tooltip>
                            );
                        },
                    },
                    {
                        title: 'Unmatched',
                        dataIndex: 'unmatched_rows',
                        key: 'unmatched_rows',
                        align: 'right',
                        render: (n) => <Text style={{ color: '#cf1322' }}>{n}</Text>,
                    },
                    {
                        title: 'Dup / Err',
                        key: 'extras',
                        align: 'right',
                        render: (_, r) => `${r.duplicate_rows} / ${r.error_rows}`,
                    },
                    {
                        title: 'Total Amount',
                        dataIndex: 'total_amount',
                        key: 'total_amount',
                        align: 'right',
                        render: (v) => <Text strong>{formatBdt(v)}</Text>,
                    },
                    {
                        title: 'Status',
                        dataIndex: 'status',
                        key: 'status',
                        render: (s) => {
                            const colors = { completed: 'green', processing: 'blue', uploaded: 'gold', failed: 'red' };
                            return <Tag color={colors[s] || 'default'}>{s}</Tag>;
                        },
                    },
                    {
                        title: 'Uploaded by',
                        key: 'uploader',
                        render: (_, r) => r.uploader?.name || '—',
                    },
                    {
                        title: 'Actions',
                        key: 'actions',
                        width: 200,
                        render: (_, record) => (
                            <Space>
                                <Button size="small" icon={<EyeOutlined />} onClick={() => onOpenDetail(record.id)}>View</Button>
                                {can('reconciliation.upload') && (
                                    <Popconfirm
                                        title="Delete this upload?"
                                        description="Transactions in this batch will be removed (donations stay)."
                                        okText="Delete"
                                        okButtonProps={{ danger: true }}
                                        onConfirm={() => handleDelete(record.id)}
                                    >
                                        <Button size="small" danger icon={<DeleteOutlined />}>Delete</Button>
                                    </Popconfirm>
                                )}
                            </Space>
                        ),
                    },
                ]}
            />

            <Modal
                title="Upload Bank Statement / External Donation File"
                open={isUploadOpen}
                onCancel={() => setIsUploadOpen(false)}
                onOk={handleUpload}
                okText="Upload & Process"
                confirmLoading={isUploading}
                destroyOnClose
                width={520}
            >
                <Alert
                    type="info"
                    showIcon
                    message="Supported: .xlsx and .csv (max 20MB). Use the template for column order."
                    style={{ marginBottom: 16 }}
                />

                <Form layout="vertical">
                    <Form.Item label="File" required>
                        <Upload
                            beforeUpload={() => false}
                            maxCount={1}
                            fileList={fileList}
                            onChange={({ fileList }) => setFileList(fileList)}
                            accept=".xlsx,.csv"
                        >
                            <Button icon={<UploadOutlined />}>Select file (.xlsx or .csv)</Button>
                        </Upload>
                    </Form.Item>

                    <Form.Item
                        label="Default Project"
                        help="Used when a row doesn't specify project_code"
                    >
                        <Select
                            placeholder="(Optional) fallback project for rows without a project_code"
                            allowClear
                            showSearch
                            optionFilterProp="label"
                            value={defaultProject || undefined}
                            onChange={setDefaultProject}
                            options={(projectsData?.data || []).map((p) => ({ value: p.id, label: `${p.project_code} — ${p.name}` }))}
                        />
                    </Form.Item>

                    <Form.Item
                        label="Auto-create donors for unmatched rows?"
                        help="If on, rows whose email/phone doesn't match an existing donor become new donor records."
                    >
                        <Switch checked={autoCreate} onChange={setAutoCreate} />
                    </Form.Item>
                </Form>
            </Modal>
        </>
    );
}

/* ========================================================================== */
/* Unmatched tab                                                               */
/* ========================================================================== */
function UnmatchedTab() {
    const { data, isLoading, isFetching, refetch } = useGetReconciliationUnmatchedQuery();
    const { data: donorsData } = useGetDonorsQuery({});
    const { data: projectsData } = useGetProjectsQuery({});
    const [matchTxn, { isLoading: isMatching }] = useMatchReconciliationTransactionMutation();

    const [matchOpen, setMatchOpen] = useState(false);
    const [activeTxn, setActiveTxn] = useState(null);
    const [donorId, setDonorId] = useState(null);
    const [projectId, setProjectId] = useState(null);

    if (isLoading) return <Spin />;

    const rows = data?.data?.transactions || [];
    const totals = data?.data?.totals;

    const openMatch = (txn) => {
        setActiveTxn(txn);
        setDonorId(null);
        setProjectId(null);
        setMatchOpen(true);
    };

    const submitMatch = async () => {
        if (!donorId) {
            message.warning('Pick a donor to link this transaction to.');
            return;
        }
        try {
            await matchTxn({ id: activeTxn.id, donor_id: donorId, project_id: projectId }).unwrap();
            message.success('Transaction matched');
            setMatchOpen(false);
            refetch();
        } catch (err) {
            message.error(err?.data?.message || 'Match failed');
        }
    };

    return (
        <>
            <Row gutter={[16, 16]} style={{ marginBottom: 16 }}>
                <Col xs={12} md={8}>
                    <Card><Statistic title="Unmatched" value={totals?.count || 0} prefix={<WarningOutlined />} valueStyle={{ color: '#cf1322' }} /></Card>
                </Col>
                <Col xs={12} md={8}>
                    <Card><Statistic title="Total Amount" value={totals?.amount || 0} suffix="POUND" /></Card>
                </Col>
            </Row>

            <Button icon={<ReloadOutlined />} onClick={refetch} style={{ marginBottom: 16 }}>Refresh</Button>

            {rows.length === 0 ? <Empty description="Nothing to reconcile" /> : (
                <Table
                    dataSource={rows}
                    rowKey="id"
                    loading={isFetching}
                    scroll={{ x: 'max-content' }}
                    columns={[
                        { title: 'Date', dataIndex: 'transaction_date', key: 'transaction_date', render: (d) => d ? new Date(d).toLocaleDateString() : '—' },
                        { title: 'From upload', key: 'upload', render: (_, r) => r.upload?.original_name || `#${r.upload_id}` },
                        { title: 'Source', dataIndex: 'source_id', key: 'source_id', render: (v) => v || '—' },
                        { title: 'Order', dataIndex: 'order_id', key: 'order_id', render: (v) => v || '—' },
                        { title: 'Name', key: 'name', render: (_, r) => [r.first_name, r.last_name].filter(Boolean).join(' ') || '—' },
                        { title: 'Email', dataIndex: 'email', key: 'email', render: (v) => v || '—' },
                        { title: 'Phone', dataIndex: 'phone', key: 'phone', render: (v) => v || '—' },
                        { title: 'Amount', dataIndex: 'amount', key: 'amount', align: 'right', render: formatBdt },
                        { title: 'Reason', dataIndex: 'notes', key: 'notes', ellipsis: true },
                        {
                            title: 'Action',
                            key: 'action',
                            render: (_, record) => (
                                <Button size="small" icon={<LinkOutlined />} onClick={() => openMatch(record)}>Match…</Button>
                            ),
                        },
                    ]}
                />
            )}

            <Modal
                title={activeTxn ? `Match transaction #${activeTxn.id}` : 'Match transaction'}
                open={matchOpen}
                onCancel={() => setMatchOpen(false)}
                onOk={submitMatch}
                okText="Match & Create Donation"
                confirmLoading={isMatching}
                destroyOnClose
            >
                {activeTxn && (
                    <Descriptions size="small" column={1} bordered style={{ marginBottom: 16 }}>
                        <Descriptions.Item label="Amount">{formatBdt(activeTxn.amount)}</Descriptions.Item>
                        <Descriptions.Item label="Name">{[activeTxn.first_name, activeTxn.last_name].filter(Boolean).join(' ') || '—'}</Descriptions.Item>
                        <Descriptions.Item label="Email">{activeTxn.email || '—'}</Descriptions.Item>
                        <Descriptions.Item label="Phone">{activeTxn.phone || '—'}</Descriptions.Item>
                    </Descriptions>
                )}
                <Form layout="vertical">
                    <Form.Item label="Donor" required>
                        <Select
                            placeholder="Search and select donor"
                            showSearch
                            optionFilterProp="label"
                            value={donorId || undefined}
                            onChange={setDonorId}
                            options={(donorsData?.data || []).map((d) => ({
                                value: d.id,
                                label: `${d.donor_id_code} — ${d.name} (${d.email || d.phone_number || 'no contact'})`,
                            }))}
                        />
                    </Form.Item>
                    <Form.Item label="Project" help="If empty, the upload's default project is used">
                        <Select
                            placeholder="Optional — defaults to upload's project"
                            allowClear
                            showSearch
                            optionFilterProp="label"
                            value={projectId || undefined}
                            onChange={setProjectId}
                            options={(projectsData?.data || []).map((p) => ({ value: p.id, label: `${p.project_code} — ${p.name}` }))}
                        />
                    </Form.Item>
                </Form>
            </Modal>
        </>
    );
}

/* ========================================================================== */
/* Upload detail drawer                                                        */
/* ========================================================================== */
function UploadDetailDrawer({ uploadId, open, onClose }) {
    const [filter, setFilter] = useState('');
    const [trigger, { data, isFetching }] = useLazyGetReconciliationUploadQuery();

    React.useEffect(() => {
        if (open && uploadId) trigger({ id: uploadId, match_status: filter });
    }, [open, uploadId, filter, trigger]);

    const upload = data?.data?.upload;
    const txns   = data?.data?.transactions || [];

    return (
        <Drawer
            title={upload ? `Upload: ${upload.original_name}` : 'Upload detail'}
            open={open}
            onClose={onClose}
            width={Math.min(1100, window.innerWidth - 40)}
            destroyOnClose
        >
            {!upload && isFetching && <Spin />}
            {upload && (
                <>
                    <Row gutter={[12, 12]} style={{ marginBottom: 16 }}>
                        <Col xs={12} md={6}><Card size="small"><Statistic title="Total rows" value={upload.total_rows} /></Card></Col>
                        <Col xs={12} md={6}><Card size="small"><Statistic title="Matched" value={upload.matched_rows} valueStyle={{ color: '#3f8600' }} /></Card></Col>
                        <Col xs={12} md={6}><Card size="small"><Statistic title="Unmatched" value={upload.unmatched_rows} valueStyle={{ color: '#cf1322' }} /></Card></Col>
                        <Col xs={12} md={6}><Card size="small"><Statistic title="Total amount" value={Number(upload.total_amount || 0)} suffix="POUND" /></Card></Col>
                    </Row>

                    <Space style={{ marginBottom: 12 }} wrap>
                        <Text>Filter:</Text>
                        <Select
                            allowClear
                            placeholder="All"
                            style={{ minWidth: 180 }}
                            value={filter || undefined}
                            onChange={(v) => setFilter(v || '')}
                            options={[
                                { value: 'matched', label: 'Matched' },
                                { value: 'donor_created', label: 'Donor created' },
                                { value: 'unmatched', label: 'Unmatched' },
                                { value: 'duplicate', label: 'Duplicate' },
                                { value: 'error', label: 'Error' },
                            ]}
                        />
                    </Space>

                    <Table
                        dataSource={txns}
                        rowKey="id"
                        loading={isFetching}
                        pagination={{ pageSize: 15 }}
                        scroll={{ x: 'max-content' }}
                        columns={[
                            { title: 'Row', dataIndex: 'row_number', key: 'row_number', width: 60 },
                            { title: 'Date', dataIndex: 'transaction_date', key: 'transaction_date', render: (d) => d ? new Date(d).toLocaleDateString() : '—' },
                            { title: 'Source', dataIndex: 'source_id', key: 'source_id', render: (v) => v || '—' },
                            { title: 'Order', dataIndex: 'order_id', key: 'order_id', render: (v) => v || '—' },
                            { title: 'Name', key: 'name', render: (_, r) => [r.first_name, r.last_name].filter(Boolean).join(' ') || '—' },
                            { title: 'Email', dataIndex: 'email', key: 'email', ellipsis: true },
                            { title: 'Amount', dataIndex: 'amount', key: 'amount', align: 'right', render: formatBdt },
                            {
                                title: 'Donor',
                                key: 'donor',
                                render: (_, r) => r.matched_donor
                                    ? `${r.matched_donor.name} (${r.matched_donor.donor_id_code})`
                                    : '—',
                            },
                            {
                                title: 'Donation',
                                key: 'donation',
                                render: (_, r) => r.created_donation?.receipt_number || '—',
                            },
                            {
                                title: 'Match status',
                                dataIndex: 'match_status',
                                key: 'match_status',
                                render: (s) => <Tag color={MATCH_COLORS[s] || 'default'}>{s}</Tag>,
                            },
                            { title: 'Notes', dataIndex: 'notes', key: 'notes', ellipsis: true },
                        ]}
                    />
                </>
            )}
        </Drawer>
    );
}

/* ========================================================================== */
/* Main page                                                                   */
/* ========================================================================== */
export default function ReconciliationPage() {
    const [activeTab, setActiveTab] = useState('uploads');
    const [detailId, setDetailId] = useState(null);

    return (
        <Card title="Bank Reconciliation">
            <Tabs
                activeKey={activeTab}
                onChange={setActiveTab}
                items={[
                    {
                        key: 'uploads',
                        label: <span><BankOutlined /> Uploads</span>,
                        children: <UploadsTab onOpenDetail={setDetailId} />,
                    },
                    {
                        key: 'unmatched',
                        label: <span><WarningOutlined /> Unmatched ({/* count appears inside the tab */}<UnmatchedBadge />)</span>,
                        children: <UnmatchedTab />,
                    },
                ]}
            />

            <UploadDetailDrawer
                uploadId={detailId}
                open={!!detailId}
                onClose={() => setDetailId(null)}
            />
        </Card>
    );
}

function UnmatchedBadge() {
    const { data } = useGetReconciliationUnmatchedQuery();
    const n = data?.data?.totals?.count || 0;
    if (!n) return null;
    return <Tag color="red" style={{ marginLeft: 4 }}>{n}</Tag>;
}
