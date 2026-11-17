<div class="space-y-4">
    @if ($invitations->isEmpty())
        <div class="text-center py-6 text-sm text-gray-500 dark:text-gray-400">
            No pending invitations for this organization.
        </div>
    @else
        <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-800">
            <table class="w-full text-left text-sm text-gray-700 dark:text-gray-300">
                <thead class="bg-gray-50 dark:bg-gray-900/50 text-xs uppercase font-medium text-gray-500 dark:text-gray-400 border-b border-gray-200 dark:border-gray-800">
                    <tr>
                        <th class="px-4 py-3">Email</th>
                        <th class="px-4 py-3">Role</th>
                        <th class="px-4 py-3">Invited By</th>
                        <th class="px-4 py-3">Expires</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                    @foreach ($invitations as $invitation)
                        <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-900/20">
                            <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">
                                {{ $invitation->email }}
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300">
                                    {{ $invitation->role->label() }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-xs text-gray-500 dark:text-gray-400">
                                {{ $invitation->inviter?->name ?? 'System' }}
                            </td>
                            <td class="px-4 py-3 text-xs text-gray-500 dark:text-gray-400">
                                {{ $invitation->expires_at->diffForHumans() }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                <button
                                    type="button"
                                    wire:click="revokeInvitation({{ $invitation->id }})"
                                    wire:confirm="Are you sure you want to revoke this invitation?"
                                    class="text-xs font-semibold text-rose-600 hover:text-rose-500 dark:text-rose-400 dark:hover:text-rose-300 transition"
                                >
                                    Revoke
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
