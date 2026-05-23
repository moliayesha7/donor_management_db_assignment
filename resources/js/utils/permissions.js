import { useSelector } from 'react-redux';
import { selectUser } from '../store/authSlice.js';

export const isSuperAdmin = (user) => user?.role?.name === 'super_admin';

export const hasPermission = (user, name) => {
    if (!user) return false;
    if (isSuperAdmin(user)) return true;
    const perms = user?.role?.permissions || [];
    return perms.some((p) => p.name === name);
};

export const hasAnyPermission = (user, names = []) =>
    names.some((n) => hasPermission(user, n));

export function usePermissions() {
    const user = useSelector(selectUser);
    return {
        user,
        can: (name) => hasPermission(user, name),
        canAny: (names) => hasAnyPermission(user, names),
        isSuperAdmin: isSuperAdmin(user),
        roleName: user?.role?.name || null,
    };
}
