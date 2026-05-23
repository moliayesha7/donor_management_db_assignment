import { createApi, fetchBaseQuery } from '@reduxjs/toolkit/query/react';
import { clearCredentials } from './authSlice.js';

const buildQuery = (params = {}) => {
    const search = new URLSearchParams();
    Object.entries(params).forEach(([key, value]) => {
        if (value !== undefined && value !== null && value !== '') {
            search.append(key, value);
        }
    });
    const qs = search.toString();
    return qs ? `?${qs}` : '';
};

const rawBaseQuery = fetchBaseQuery({
    baseUrl: '/api/',
    prepareHeaders: (headers, { getState }) => {
        const token = getState()?.auth?.token;
        if (token) headers.set('Authorization', `Bearer ${token}`);
        headers.set('Accept', 'application/json');
        return headers;
    },
});

const baseQueryWithAuth = async (args, api, extraOptions) => {
    const result = await rawBaseQuery(args, api, extraOptions);
    if (result?.error?.status === 401) {
        api.dispatch(clearCredentials());
    }
    return result;
};

export const apiSlice = createApi({
    reducerPath: 'api',
    baseQuery: baseQueryWithAuth,
    tagTypes: ['Project', 'ProjectType', 'User', 'Role', 'Donor','DonorSource', 'Email', 'EmailSchedule', 'EmailLog', 'EmailTemplate','SmsTemplate','SmsSchedule','Student','Donation','Campaign','Expense','Report','Reconciliation','RecycleBin','ActivityLog', 'Me'],
    endpoints: (builder) => ({
        // ===== Auth =====
        login: builder.mutation({
            query: (body) => ({ url: 'login', method: 'POST', body }),
        }),
        register: builder.mutation({
            query: (body) => ({ url: 'register', method: 'POST', body }),
        }),
        logout: builder.mutation({
            query: () => ({ url: 'logout', method: 'POST' }),
        }),
        me: builder.query({
            query: () => 'me',
            providesTags: [{ type: 'Me', id: 'CURRENT' }],
        }),

        // ===== Projects =====
        getProjects: builder.query({
            query: (params = {}) => `projects${buildQuery(params)}`,
            providesTags: (result) =>
                result?.data
                    ? [
                          ...result.data.map(({ id }) => ({ type: 'Project', id })),
                          { type: 'Project', id: 'LIST' },
                      ]
                    : [{ type: 'Project', id: 'LIST' }],
        }),
        getProject: builder.query({
            query: (id) => `projects/${id}`,
            providesTags: (result, error, id) => [{ type: 'Project', id }],
        }),
        createProject: builder.mutation({
            query: (newProject) => ({
                url: 'projects',
                method: 'POST',
                body: newProject,
            }),
            invalidatesTags: [{ type: 'Project', id: 'LIST' }],
        }),
        updateProject: builder.mutation({
            query: ({ id, ...patch }) => ({
                url: `projects/${id}`,
                method: 'PUT',
                body: patch,
            }),
            invalidatesTags: (result, error, { id }) => [
                { type: 'Project', id },
                { type: 'Project', id: 'LIST' },
            ],
        }),
        deleteProject: builder.mutation({
            query: (id) => ({
                url: `projects/${id}`,
                method: 'DELETE',
            }),
            invalidatesTags: (result, error, id) => [
                { type: 'Project', id },
                { type: 'Project', id: 'LIST' },
            ],
        }),

        // ===== Project Types =====
        getProjectTypes: builder.query({
            query: (params = {}) => `project-types${buildQuery(params)}`,
            providesTags: (result) =>
                result?.data
                    ? [
                          ...result.data.map(({ id }) => ({ type: 'ProjectType', id })),
                          { type: 'ProjectType', id: 'LIST' },
                      ]
                    : [{ type: 'ProjectType', id: 'LIST' }],
        }),
        getProjectType: builder.query({
            query: (id) => `project-types/${id}`,
            providesTags: (result, error, id) => [{ type: 'ProjectType', id }],
        }),
        createProjectType: builder.mutation({
            query: (body) => ({
                url: 'project-types',
                method: 'POST',
                body,
            }),
            invalidatesTags: [{ type: 'ProjectType', id: 'LIST' }],
        }),
        updateProjectType: builder.mutation({
            query: ({ id, ...patch }) => ({
                url: `project-types/${id}`,
                method: 'PUT',
                body: patch,
            }),
            invalidatesTags: (result, error, { id }) => [
                { type: 'ProjectType', id },
                { type: 'ProjectType', id: 'LIST' },
            ],
        }),
        deleteProjectType: builder.mutation({
            query: (id) => ({
                url: `project-types/${id}`,
                method: 'DELETE',
            }),
            invalidatesTags: (result, error, id) => [
                { type: 'ProjectType', id },
                { type: 'ProjectType', id: 'LIST' },
                { type: 'Project', id: 'LIST' },
            ],
        }),

        // ===== Roles =====
        getRoles: builder.query({
            query: () => 'roles',
            providesTags: [{ type: 'Role', id: 'LIST' }],
        }),

        // ===== Users =====
        getUsers: builder.query({
            query: (params = {}) => `users${buildQuery(params)}`,
            providesTags: (result) =>
                result?.data
                    ? [
                          ...result.data.map(({ id }) => ({ type: 'User', id })),
                          { type: 'User', id: 'LIST' },
                      ]
                    : [{ type: 'User', id: 'LIST' }],
        }),
        getUser: builder.query({
            query: (id) => `users/${id}`,
            providesTags: (result, error, id) => [{ type: 'User', id }],
        }),
        createUser: builder.mutation({
            query: (body) => ({
                url: 'users',
                method: 'POST',
                body,
            }),
            invalidatesTags: [{ type: 'User', id: 'LIST' }],
        }),
        updateUser: builder.mutation({
            query: ({ id, ...patch }) => ({
                url: `users/${id}`,
                method: 'PUT',
                body: patch,
            }),
            invalidatesTags: (result, error, { id }) => [
                { type: 'User', id },
                { type: 'User', id: 'LIST' },
            ],
        }),
        deleteUser: builder.mutation({
            query: (id) => ({
                url: `users/${id}`,
                method: 'DELETE',
            }),
            invalidatesTags: (result, error, id) => [
                { type: 'User', id },
                { type: 'User', id: 'LIST' },
            ],
        }),

        // ===== Donors =====
        getDonors: builder.query({
            query: (params = {}) => `donors${buildQuery(params)}`,
            providesTags: (result) =>
                result?.data
                    ? [
                          ...result.data.map(({ id }) => ({ type: 'Donor', id })),
                          { type: 'Donor', id: 'LIST' },
                      ]
                    : [{ type: 'Donor', id: 'LIST' }],
        }),
        getDonor: builder.query({
            query: (id) => `donors/${id}`,
            providesTags: (result, error, id) => [{ type: 'Donor', id }],
        }),
        createDonor: builder.mutation({
            query: (body) => ({
                url: 'donors',
                method: 'POST',
                body,
            }),
            invalidatesTags: [{ type: 'Donor', id: 'LIST' }],
        }),
        updateDonor: builder.mutation({
            query: ({ id, ...patch }) => ({
                url: `donors/${id}`,
                method: 'PUT',
                body: patch,
            }),
            invalidatesTags: (result, error, { id }) => [
                { type: 'Donor', id },
                { type: 'Donor', id: 'LIST' },
            ],
        }),
        deleteDonor: builder.mutation({
            query: (id) => ({
                url: `donors/${id}`,
                method: 'DELETE',
            }),
            invalidatesTags: (result, error, id) => [
                { type: 'Donor', id },
                { type: 'Donor', id: 'LIST' },
            ],
        }),
        // ===== Donor Sources =====
        getDonorSources: builder.query({
            query: (params = {}) => `donor-sources${buildQuery(params)}`,
            providesTags: (result) =>
                result?.data
                    ? [
                          ...result.data.map(({ id }) => ({ type: 'DonorSource', id })),
                          { type: 'DonorSource', id: 'LIST' },
                      ]
                    : [{ type: 'DonorSource', id: 'LIST' }],
        }),
        getDonorSource: builder.query({
            query: (id) => `donor-sources/${id}`,
            providesTags: (result, error, id) => [{ type: 'DonorSource', id }],
        }),
        createDonorSource: builder.mutation({
            query: (body) => ({
                url: 'donor-sources',
                method: 'POST',
                body,
            }),
            invalidatesTags: [{ type: 'DonorSource', id: 'LIST' }],
        }),
        updateDonorSource: builder.mutation({
            query: ({ id, ...patch }) => ({
                url: `donor-sources/${id}`,
                method: 'PUT',
                body: patch,
            }),
            invalidatesTags: (result, error, { id }) => [
                { type: 'DonorSource', id },
                { type: 'DonorSource', id: 'LIST' },
            ],
        }),
        deleteDonorSource: builder.mutation({
            query: (id) => ({
                url: `donor-sources/${id}`,
                method: 'DELETE',
            }),
            invalidatesTags: (result, error, id) => [
                { type: 'DonorSource', id },
                { type: 'DonorSource', id: 'LIST' },
            ],
        }),
        // ===== Email Templates =====
        getEmailTemplates: builder.query({
            query: (params) => ({ url: 'email-templates', params }),
            providesTags: ['EmailTemplate'],
        }),
        createEmailTemplate: builder.mutation({
            query: (body) => ({ url: 'email-templates', method: 'POST', body }),
            invalidatesTags: ['EmailTemplate'],
        }),
        updateEmailTemplate: builder.mutation({
            query: ({ id, ...body }) => ({ url: `email-templates/${id}`, method: 'PUT', body }),
            invalidatesTags: ['EmailTemplate'],
        }),
        deleteEmailTemplate: builder.mutation({
            query: (id) => ({ url: `email-templates/${id}`, method: 'DELETE' }),
            invalidatesTags: ['EmailTemplate'],
        }),
        // tagTypes এ যোগ করুন: 'SmsTemplate'
            getSmsTemplates: builder.query({
                query: (params) => ({ url: 'sms-templates', params }),
                providesTags: ['SmsTemplate'],
            }),
            createSmsTemplate: builder.mutation({
                query: (body) => ({ url: 'sms-templates', method: 'POST', body }),
                invalidatesTags: ['SmsTemplate'],
            }),
            updateSmsTemplate: builder.mutation({
                query: ({ id, ...body }) => ({ url: `sms-templates/${id}`, method: 'PUT', body }),
                invalidatesTags: ['SmsTemplate'],
            }),
            deleteSmsTemplate: builder.mutation({
                query: (id) => ({ url: `sms-templates/${id}`, method: 'DELETE' }),
                invalidatesTags: ['SmsTemplate'],
            }),
            getSmsSchedules: builder.query({
                query: (params) => ({ url: 'sms-schedules', params }),
                providesTags: ['SmsTemplate'],
            }),
            getSmsLogs: builder.query({
                query: (params) => ({ url: 'sms-logs', params }),
                providesTags: ['SmsTemplate'],
            }),
            // tagTypes এ 'Student' যোগ করে নিচের এন্ডপয়েন্টগুলো সেট করুন:
getStudents: builder.query({
    query: (params) => ({ url: 'students', params }),
    providesTags: ['Student'],
}),
createStudent: builder.mutation({
    query: (body) => ({ url: 'students', method: 'POST', body }),
    invalidatesTags: ['Student'],
}),
updateStudent: builder.mutation({
    query: ({ id, ...body }) => ({ url: `students/${id}`, method: 'PUT', body }),
    invalidatesTags: ['Student'],
}),
deleteStudent: builder.mutation({
    query: (id) => ({ url: `students/${id}`, method: 'DELETE' }),
    invalidatesTags: ['Student'],
}),
// tagTypes অ্যারেতে 'Donation' যোগ করুন
getDonations: builder.query({
    query: (params) => ({ url: 'donations', params }),
    providesTags: ['Donation'],
}),
createDonation: builder.mutation({
    query: (body) => ({ url: 'donations', method: 'POST', body }),
    invalidatesTags: ['Donation'],
}),
updateDonation: builder.mutation({
    query: ({ id, ...body }) => ({ url: `donations/${id}`, method: 'PUT', body }),
    invalidatesTags: ['Donation'],
}),
deleteDonation: builder.mutation({
    query: (id) => ({ url: `donations/${id}`, method: 'DELETE' }),
    invalidatesTags: ['Donation'],
}),

// ===== Activity / Audit Logs =====
getActivityLogs: builder.query({
    query: (params = {}) => ({ url: 'activity-logs', params }),
    providesTags: ['ActivityLog'],
}),

// ===== Campaigns =====
getCampaigns: builder.query({
    query: (params = {}) => ({ url: 'campaigns', params }),
    providesTags: ['Campaign'],
}),
getCampaign: builder.query({
    query: (id) => `campaigns/${id}`,
    providesTags: (r, e, id) => [{ type: 'Campaign', id }],
}),
createCampaign: builder.mutation({
    query: (body) => ({ url: 'campaigns', method: 'POST', body }),
    invalidatesTags: ['Campaign'],
}),
updateCampaign: builder.mutation({
    query: ({ id, ...body }) => ({ url: `campaigns/${id}`, method: 'PUT', body }),
    invalidatesTags: ['Campaign'],
}),
deleteCampaign: builder.mutation({
    query: (id) => ({ url: `campaigns/${id}`, method: 'DELETE' }),
    invalidatesTags: ['Campaign'],
}),

// ===== Expenses =====
getExpenses: builder.query({
    query: (params = {}) => `expenses${buildQuery(params)}`,
    providesTags: (result) =>
        result?.data?.expenses
            ? [
                  ...result.data.expenses.map(({ id }) => ({ type: 'Expense', id })),
                  { type: 'Expense', id: 'LIST' },
              ]
            : [{ type: 'Expense', id: 'LIST' }],
}),
getExpense: builder.query({
    query: (id) => `expenses/${id}`,
    providesTags: (r, e, id) => [{ type: 'Expense', id }],
}),
createExpense: builder.mutation({
    query: (body) => ({ url: 'expenses', method: 'POST', body }),
    // Mutations on expenses change report numbers too — invalidate Report tag.
    invalidatesTags: [
        { type: 'Expense', id: 'LIST' },
        { type: 'Report', id: 'PROJECT_WISE' },
        { type: 'Report', id: 'DONATION_SUMMARY' },
    ],
}),
updateExpense: builder.mutation({
    query: ({ id, ...body }) => ({ url: `expenses/${id}`, method: 'PUT', body }),
    invalidatesTags: (r, e, { id }) => [
        { type: 'Expense', id },
        { type: 'Expense', id: 'LIST' },
        { type: 'Report', id: 'PROJECT_WISE' },
    ],
}),
deleteExpense: builder.mutation({
    query: (id) => ({ url: `expenses/${id}`, method: 'DELETE' }),
    invalidatesTags: (r, e, id) => [
        { type: 'Expense', id },
        { type: 'Expense', id: 'LIST' },
        { type: 'Report', id: 'PROJECT_WISE' },
    ],
}),

// ===== Reports =====
getProjectWiseReport: builder.query({
    query: (params = {}) => `reports/project-wise${buildQuery(params)}`,
    providesTags: [{ type: 'Report', id: 'PROJECT_WISE' }],
}),
getProjectReportDetail: builder.query({
    query: (id) => `reports/project/${id}/detail`,
    providesTags: (r, e, id) => [{ type: 'Report', id: `PROJECT_${id}` }],
}),
getDonationSummary: builder.query({
    query: (params = {}) => `reports/donation-summary${buildQuery(params)}`,
    providesTags: [{ type: 'Report', id: 'DONATION_SUMMARY' }],
}),

// ===== Recycle Bin / Backup / Receipts =====
getRecycleBin: builder.query({
    query: () => 'recycle-bin',
    providesTags: [{ type: 'RecycleBin', id: 'INDEX' }],
}),
restoreRecycleBinItem: builder.mutation({
    query: ({ type, id }) => ({ url: `recycle-bin/${type}/${id}/restore`, method: 'POST' }),
    invalidatesTags: [
        { type: 'RecycleBin', id: 'INDEX' },
        { type: 'Donor', id: 'LIST' },
        { type: 'Project', id: 'LIST' },
        { type: 'Donation', id: 'LIST' },
        { type: 'Expense', id: 'LIST' },
    ],
}),
forceDeleteRecycleBinItem: builder.mutation({
    query: ({ type, id }) => ({ url: `recycle-bin/${type}/${id}`, method: 'DELETE' }),
    invalidatesTags: [{ type: 'RecycleBin', id: 'INDEX' }],
}),
emptyRecycleBin: builder.mutation({
    query: () => ({ url: 'recycle-bin/empty', method: 'POST' }),
    invalidatesTags: [{ type: 'RecycleBin', id: 'INDEX' }],
}),
getReceiptUrl: builder.query({
    query: (donationId) => `donations/${donationId}/receipt-url`,
}),
getBackupUrl: builder.query({
    query: () => 'backup/url',
}),

// ===== Notification logs (Email / SMS / WhatsApp) =====
getEmailLogs: builder.query({
    query: (params = {}) => `email-logs${buildQuery(params)}`,
    providesTags: [{ type: 'EmailLog', id: 'LIST' }],
}),
getWhatsappLogs: builder.query({
    query: (params = {}) => `whatsapp/logs${buildQuery(params)}`,
    providesTags: [{ type: 'EmailLog', id: 'WA_LIST' }],
}),
retryEmailLog: builder.mutation({
    query: (id) => ({ url: `email-logs/${id}/retry`, method: 'POST' }),
    invalidatesTags: [{ type: 'EmailLog', id: 'LIST' }],
}),
retrySmsLog: builder.mutation({
    query: (id) => ({ url: `sms-logs/${id}/retry`, method: 'POST' }),
    invalidatesTags: ['SmsTemplate'],
}),
retryWhatsappLog: builder.mutation({
    query: (id) => ({ url: `whatsapp-logs/${id}/retry`, method: 'POST' }),
    invalidatesTags: [{ type: 'EmailLog', id: 'WA_LIST' }],
}),

// ===== Financial Reports (Part 8) =====
getCashFlowReport: builder.query({
    query: (params = {}) => `reports/cash-flow${buildQuery(params)}`,
    providesTags: [{ type: 'Report', id: 'CASH_FLOW' }],
}),
getDonationLedger: builder.query({
    query: (params = {}) => `reports/donation-ledger${buildQuery(params)}`,
    providesTags: [{ type: 'Report', id: 'DONATION_LEDGER' }],
}),
getProjectBalanceReport: builder.query({
    query: () => 'reports/project-balance',
    providesTags: [{ type: 'Report', id: 'PROJECT_BALANCE' }],
}),
getFinancialReconciliationReport: builder.query({
    query: () => 'reports/financial-reconciliation',
    providesTags: [
        { type: 'Report', id: 'FINANCIAL_RECON' },
        { type: 'Reconciliation', id: 'LIST' },
    ],
}),

// ===== Bank Reconciliation =====
getReconciliationUploads: builder.query({
    query: () => 'reconciliation/uploads',
    providesTags: (result) =>
        result?.data?.uploads
            ? [
                  ...result.data.uploads.map(({ id }) => ({ type: 'Reconciliation', id })),
                  { type: 'Reconciliation', id: 'LIST' },
              ]
            : [{ type: 'Reconciliation', id: 'LIST' }],
}),
getReconciliationUpload: builder.query({
    query: ({ id, match_status }) => {
        const qs = match_status ? `?match_status=${match_status}` : '';
        return `reconciliation/uploads/${id}${qs}`;
    },
    providesTags: (r, e, arg) => [{ type: 'Reconciliation', id: arg.id }],
}),
getReconciliationUnmatched: builder.query({
    query: () => 'reconciliation/unmatched',
    providesTags: [{ type: 'Reconciliation', id: 'UNMATCHED' }],
}),
uploadReconciliation: builder.mutation({
    query: (formData) => ({
        url: 'reconciliation/uploads',
        method: 'POST',
        body: formData,
        // Let the browser set Content-Type with the multipart boundary.
        formData: true,
    }),
    invalidatesTags: [
        { type: 'Reconciliation', id: 'LIST' },
        { type: 'Reconciliation', id: 'UNMATCHED' },
        { type: 'Report', id: 'FINANCIAL_RECON' },
        { type: 'Report', id: 'PROJECT_WISE' },
        { type: 'Report', id: 'CASH_FLOW' },
        { type: 'Donation', id: 'LIST' },
        { type: 'Donor', id: 'LIST' },
    ],
}),
deleteReconciliationUpload: builder.mutation({
    query: (id) => ({
        url: `reconciliation/uploads/${id}`,
        method: 'DELETE',
    }),
    invalidatesTags: (r, e, id) => [
        { type: 'Reconciliation', id },
        { type: 'Reconciliation', id: 'LIST' },
        { type: 'Reconciliation', id: 'UNMATCHED' },
        { type: 'Report', id: 'FINANCIAL_RECON' },
    ],
}),
matchReconciliationTransaction: builder.mutation({
    query: ({ id, ...body }) => ({
        url: `reconciliation/transactions/${id}/match`,
        method: 'POST',
        body,
    }),
    invalidatesTags: [
        { type: 'Reconciliation', id: 'LIST' },
        { type: 'Reconciliation', id: 'UNMATCHED' },
        { type: 'Report', id: 'FINANCIAL_RECON' },
        { type: 'Donation', id: 'LIST' },
    ],
}),
            }),
});

export const {
    useLoginMutation,
    useRegisterMutation,
    useLogoutMutation,
    useMeQuery,
    useLazyMeQuery,
    useGetProjectsQuery,
    useGetProjectQuery,
    useCreateProjectMutation,
    useUpdateProjectMutation,
    useDeleteProjectMutation,
    useGetProjectTypesQuery,
    useGetProjectTypeQuery,
    useCreateProjectTypeMutation,
    useUpdateProjectTypeMutation,
    useDeleteProjectTypeMutation,
    useGetRolesQuery,
    useGetUsersQuery,
    useGetUserQuery,
    useCreateUserMutation,
    useUpdateUserMutation,
    useDeleteUserMutation,
    useGetDonorsQuery,
    useGetDonorQuery,
    useLazyGetDonorQuery,
    useCreateDonorMutation,
    useUpdateDonorMutation,
    useDeleteDonorMutation,
    useGetDonorSourcesQuery,
    useGetDonorSourceQuery,
    useCreateDonorSourceMutation,
    useUpdateDonorSourceMutation,
    useDeleteDonorSourceMutation,
    useGetEmailTemplatesQuery, 
    useCreateEmailTemplateMutation, 
    useUpdateEmailTemplateMutation, 
    useDeleteEmailTemplateMutation,
    useGetSmsTemplatesQuery, 
    useCreateSmsTemplateMutation, 
    useUpdateSmsTemplateMutation, 
    useDeleteSmsTemplateMutation,
    useGetSmsSchedulesQuery, 
    useGetSmsLogsQuery,
    useGetStudentsQuery, 
    useCreateStudentMutation, 
    useUpdateStudentMutation, 
    useDeleteStudentMutation,
    useGetDonationsQuery,
    useCreateDonationMutation,
    useUpdateDonationMutation,
    useDeleteDonationMutation,
    useGetCampaignsQuery,
    useGetCampaignQuery,
    useCreateCampaignMutation,
    useUpdateCampaignMutation,
    useDeleteCampaignMutation,
    useGetActivityLogsQuery,
    useGetExpensesQuery,
    useGetExpenseQuery,
    useCreateExpenseMutation,
    useUpdateExpenseMutation,
    useDeleteExpenseMutation,
    useGetProjectWiseReportQuery,
    useGetProjectReportDetailQuery,
    useLazyGetProjectReportDetailQuery,
    useGetDonationSummaryQuery,
    useGetCashFlowReportQuery,
    useGetDonationLedgerQuery,
    useGetProjectBalanceReportQuery,
    useGetFinancialReconciliationReportQuery,
    useGetReconciliationUploadsQuery,
    useGetReconciliationUploadQuery,
    useLazyGetReconciliationUploadQuery,
    useGetReconciliationUnmatchedQuery,
    useUploadReconciliationMutation,
    useDeleteReconciliationUploadMutation,
    useMatchReconciliationTransactionMutation,
    useGetEmailLogsQuery,
    useGetWhatsappLogsQuery,
    useRetryEmailLogMutation,
    useRetrySmsLogMutation,
    useRetryWhatsappLogMutation,
    useGetRecycleBinQuery,
    useRestoreRecycleBinItemMutation,
    useForceDeleteRecycleBinItemMutation,
    useEmptyRecycleBinMutation,
    useLazyGetReceiptUrlQuery,
    useLazyGetBackupUrlQuery,
} = apiSlice;
