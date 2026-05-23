import React, { useState, useMemo } from 'react';
import { useDispatch } from 'react-redux';
import {
    Layout, Menu, Button, Avatar, Dropdown, Typography, Grid, theme, message, Tag,
} from 'antd';
import {
    MenuFoldOutlined, MenuUnfoldOutlined, ProjectOutlined, TagsOutlined,
    UserOutlined, LogoutOutlined, TeamOutlined, ReadOutlined, DollarOutlined,
    BellOutlined, FileTextOutlined, BankOutlined, DashboardOutlined,
    SafetyCertificateOutlined, ShareAltOutlined, WalletOutlined,
    DeleteOutlined,
} from '@ant-design/icons';
import { useLogoutMutation } from '../store/apiSlice.js';
import { clearCredentials } from '../store/authSlice.js';
import { usePermissions } from '../utils/permissions.js';
import ProjectsPage from '../pages/ProjectsPage.jsx';
import ProjectTypesPage from '../pages/ProjectTypesPage.jsx';
import UsersPage from '../pages/UsersPage.jsx';
import DonorsPage from '../pages/DonorsPage.jsx';
import DonorSourcesPage from '../pages/DonorSourcesPage.jsx';
import StudentsPage from '../pages/StudentsPage.jsx';
import DonationsPage from '../pages/DonationsPage.jsx';
import ExpensesPage from '../pages/ExpensesPage.jsx';
import ReportsPage from '../pages/ReportsPage.jsx';
import ReconciliationPage from '../pages/ReconciliationPage.jsx';
import NotificationsPage from '../pages/NotificationsPage.jsx';
import DashboardPage from '../pages/DashboardPage.jsx';
import RecycleBinPage from '../pages/RecycleBinPage.jsx';
import AuditLogsPage from '../pages/AuditLogTable.jsx';

const { Header, Sider, Content } = Layout;
const { useBreakpoint } = Grid;
const { Text } = Typography;

const ComingSoon = ({ label }) => (
    <div
        style={{
            padding: 48,
            textAlign: 'center',
            background: '#fff',
            borderRadius: 8,
            color: '#888',
        }}
    >
        <h3>{label}</h3>
        <p>This module is not implemented yet.</p>
    </div>
);

// Each item declares which permission(s) gate it. `permission: null` means
// always visible (e.g. Dashboard). canAny() returns true for super_admin.
const NAV_DEFINITIONS = [
    { key: 'dashboard',      icon: <DashboardOutlined />,          label: 'Dashboard',          permission: null },
    { key: 'projects',       icon: <ProjectOutlined />,            label: 'Projects',           permission: 'projects.view' },
    { key: 'project-types',  icon: <TagsOutlined />,               label: 'Project Types',      permission: 'project-types.view' },
    { key: 'donors',         icon: <TeamOutlined />,               label: 'Donors',             permission: 'donors.view' },
    { key: 'donor-sources',  icon: <ShareAltOutlined />,           label: 'Donor Sources',      permission: 'donor-sources.view' },
    { key: 'students',       icon: <ReadOutlined />,               label: 'Students',           permission: 'students.view' },
    { key: 'donations',      icon: <DollarOutlined />,             label: 'Donations',          permission: 'donations.view' },
    { key: 'expenses',       icon: <WalletOutlined />,             label: 'Expenses',           permission: 'expenses.view' },
    { key: 'notifications',  icon: <BellOutlined />,               label: 'Notifications',      permission: 'notifications.view' },
    { key: 'reports',        icon: <FileTextOutlined />,           label: 'Reports',            permission: 'reports.view' },
    { key: 'reconciliation', icon: <BankOutlined />,               label: 'Bank Reconciliation', permission: 'reconciliation.view' },
    { key: 'users',          icon: <SafetyCertificateOutlined />,  label: 'Users & Roles',      permission: 'users.view' },
    { key: 'recycle-bin',    icon: <DeleteOutlined />,             label: 'Recycle Bin',        permission: 'recycle-bin.view' },
    { key: 'audit-logs', icon: <SafetyCertificateOutlined />, label: 'Activity Logs', permission: 'audit-logs.view' },
];

export default function AppShell() {
    const screens = useBreakpoint();
    const isMobile = !screens.md;
    const [collapsed, setCollapsed] = useState(false);
    const [mobileOpen, setMobileOpen] = useState(false);
    const dispatch = useDispatch();
    const { user, can, roleName } = usePermissions();
    const [logout, { isLoading: isLoggingOut }] = useLogoutMutation();
    const { token } = theme.useToken();

    // Filter nav items by the user's permission set, then default to the first allowed item.
    const navItems = useMemo(
        () => NAV_DEFINITIONS.filter((i) => !i.permission || can(i.permission)),
        [user]
    );
    const pageTitles = useMemo(
        () => Object.fromEntries(NAV_DEFINITIONS.map((i) => [i.key, i.label])),
        []
    );

    const [active, setActive] = useState(() => navItems[0]?.key || 'dashboard');

    const handleLogout = async () => {
        try {
            await logout().unwrap();
        } catch {
            // If the server call fails, still clear local creds.
        }
        dispatch(clearCredentials());
        message.success('Logged out');
    };

    const renderPage = () => {
        switch (active) {
            case 'dashboard':     return <DashboardPage />;
            case 'projects':      return can('projects.view')      ? <ProjectsPage />     : <ComingSoon label="No access" />;
            case 'project-types': return can('project-types.view') ? <ProjectTypesPage /> : <ComingSoon label="No access" />;
            case 'donors':        return can('donors.view')        ? <DonorsPage />       : <ComingSoon label="No access" />;
            case 'donor-sources': return can('donor-sources.view') ? <DonorSourcesPage /> : <ComingSoon label="No access" />;
            case 'students':      return can('students.view')      ? <StudentsPage />     : <ComingSoon label="No access" />;
            case 'donations':     return can('donations.view')     ? <DonationsPage />    : <ComingSoon label="No access" />;
            case 'expenses':      return can('expenses.view')      ? <ExpensesPage />     : <ComingSoon label="No access" />;
            case 'notifications': return can('notifications.view') ? <NotificationsPage /> : <ComingSoon label="No access" />;
            case 'reports':       return can('reports.view')       ? <ReportsPage />      : <ComingSoon label="No access" />;
            case 'reconciliation':return can('reconciliation.view')? <ReconciliationPage />: <ComingSoon label="No access" />;
            case 'users':         return can('users.view')         ? <UsersPage />        : <ComingSoon label="No access" />;
            case 'recycle-bin':   return can('recycle-bin.view')   ? <RecycleBinPage />   : <ComingSoon label="No access" />;
            case 'audit-logs':    return can('audit-logs.view')    ? <AuditLogsPage />    : <ComingSoon label="No access" />;
            default:              return <ComingSoon label={pageTitles[active] || 'Coming soon'} />;
        }
    };

    const onMenuClick = ({ key }) => {
        setActive(key);
        if (isMobile) setMobileOpen(false);
    };

    const userMenu = {
        items: [
            {
                key: 'profile',
                icon: <UserOutlined />,
                label: (
                    <div>
                        <div style={{ fontWeight: 500 }}>{user?.name || 'User'}</div>
                        <Text type="secondary" style={{ fontSize: 12 }}>{user?.email}</Text>
                    </div>
                ),
                disabled: true,
            },
            { type: 'divider' },
            {
                key: 'logout',
                icon: <LogoutOutlined />,
                label: 'Logout',
                danger: true,
                onClick: handleLogout,
            },
        ],
    };

    const siderProps = isMobile
        ? {
              breakpoint: 'md',
              collapsedWidth: 0,
              collapsed: !mobileOpen,
              onCollapse: (c) => setMobileOpen(!c),
              trigger: null,
              style: {
                  position: 'fixed',
                  height: '100vh',
                  zIndex: 1001,
                  left: 0,
                  top: 0,
                  boxShadow: mobileOpen ? '2px 0 8px rgba(0,0,0,0.2)' : 'none',
              },
          }
        : {
              collapsible: true,
              collapsed,
              onCollapse: setCollapsed,
              trigger: null,
              width: 240,
              style: { height: '100vh', position: 'sticky', top: 0, left: 0 },
          };

    return (
        <Layout style={{ minHeight: '100vh' }}>
            {isMobile && mobileOpen && (
                <div
                    onClick={() => setMobileOpen(false)}
                    style={{
                        position: 'fixed',
                        inset: 0,
                        background: 'rgba(0,0,0,0.45)',
                        zIndex: 1000,
                    }}
                />
            )}
            <Sider {...siderProps} theme="dark">
                <div
                    style={{
                        height: 64,
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                        color: '#fff',
                        fontWeight: 600,
                        fontSize: collapsed && !isMobile ? 16 : 18,
                        borderBottom: '1px solid rgba(255,255,255,0.1)',
                    }}
                >
                    {collapsed && !isMobile ? 'DM' : 'Donor Mgmt'}
                </div>
                <Menu
                    theme="dark"
                    mode="inline"
                    selectedKeys={[active]}
                    items={navItems}
                    onClick={onMenuClick}
                />
            </Sider>

            <Layout>
                <Header
                    style={{
                        padding: '0 16px',
                        background: token.colorBgContainer,
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'space-between',
                        boxShadow: '0 1px 4px rgba(0,0,0,0.08)',
                        position: 'sticky',
                        top: 0,
                        zIndex: 100,
                    }}
                >
                    <div style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
                        <Button
                            type="text"
                            icon={
                                isMobile
                                    ? <MenuUnfoldOutlined />
                                    : (collapsed ? <MenuUnfoldOutlined /> : <MenuFoldOutlined />)
                            }
                            onClick={() => {
                                if (isMobile) setMobileOpen((v) => !v);
                                else setCollapsed((v) => !v);
                            }}
                            style={{ fontSize: 18 }}
                        />
                        <Text strong style={{ fontSize: 16 }}>
                            {pageTitles[active] || 'Donor Management'}
                        </Text>
                    </div>

                    <Dropdown menu={userMenu} placement="bottomRight" trigger={['click']}>
                        <div style={{ cursor: 'pointer', display: 'flex', alignItems: 'center', gap: 8 }}>
                            <Avatar style={{ background: token.colorPrimary }} icon={<UserOutlined />} />
                            {!isMobile && (
                                <div style={{ lineHeight: 1.2 }}>
                                    <div style={{ fontWeight: 500 }}>{user?.name || 'User'}</div>
                                    <Tag color="blue" style={{ marginRight: 0, fontSize: 11 }}>
                                        {roleName || '—'}
                                    </Tag>
                                </div>
                            )}
                        </div>
                    </Dropdown>
                </Header>

                <Content style={{ padding: isMobile ? 12 : 24, background: '#f5f5f5' }}>
                    {renderPage()}
                </Content>
            </Layout>
        </Layout>
    );
}
