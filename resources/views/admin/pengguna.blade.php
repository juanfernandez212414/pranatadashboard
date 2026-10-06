{{-- resources/views/admin/pengguna.blade.php --}}

<x-adminlayout title="Manajemen Pengguna - Admin PRANATA">
    {{-- Memanggil fungsi penggunaData dari admin.js dan mengirim variabel PHP --}}
    <div x-data="penggunaData('{{ $search ?? '' }}', '{{ route('admin.pengguna') }}')"
        @keydown.escape.window="showEditModal = false; showDeleteModal = false; showAddModal = false">

        {{-- ======================== --}}
        {{--    Modal Tambah Pengguna   --}}
        {{-- ======================== --}}
        <div x-show="showAddModal" class="fixed inset-0 z-50 flex items-center justify-center p-4" x-cloak>

            {{-- Latar Belakang Transparan & Blur (Sudah Diperbaiki) --}}
            <div x-show="showAddModal" x-transition.opacity class="fixed inset-0 bg-black/50 backdrop-blur-sm"
                @click="showAddModal = false">
            </div>

            <div x-show="showAddModal" x-transition:enter="ease-out duration-300"
                x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100 scale-100"
                x-transition:leave-end="opacity-0 scale-95"
                class="relative bg-white rounded-2xl shadow-xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between p-5 border-b rounded-t sticky top-0 bg-white z-10">
                    <h3 class="text-xl font-semibold text-gray-900">Tambah Pengguna Baru</h3>
                    <button type="button" @click="showAddModal = false"
                        class="text-gray-400 bg-transparent hover:bg-gray-200 hover:text-gray-900 rounded-lg text-sm w-8 h-8 ms-auto inline-flex justify-center items-center">
                        <svg class="w-3 h-3" fill="none" viewBox="0 0 14 14">
                            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="m1 1 6 6m0 0 6 6M7 7l6-6M7 7l-6 6" />
                        </svg>
                    </button>
                </div>

                <form action="{{ route('admin.pengguna.store') }}" method="POST">
                    @csrf
                    <div class="p-6 space-y-4">
                        <div>
                            <label for="name" class="block mb-2 text-sm font-medium text-gray-900">Nama
                                Lengkap</label>
                            <input type="text" name="name" id="name" value="{{ old('name') }}"
                                class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-[#002D72] focus:border-[#002D72] block w-full p-2.5"
                                placeholder="Masukkan nama lengkap" required>
                            @error('name')
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label for="email" class="block mb-2 text-sm font-medium text-gray-900">Email</label>
                            <input type="email" name="email" id="email" value="{{ old('email') }}"
                                class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-[#002D72] focus:border-[#002D72] block w-full p-2.5"
                                placeholder="nama@email.com" required>
                            @error('email')
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label for="password" class="block mb-2 text-sm font-medium text-gray-900">Password</label>
                            <input type="password" name="password" id="password"
                                class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-[#002D72] focus:border-[#002D72] block w-full p-2.5"
                                placeholder="••••••••" required>
                            @error('password')
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label for="password_confirmation"
                                class="block mb-2 text-sm font-medium text-gray-900">Konfirmasi Password</label>
                            <input type="password" name="password_confirmation" id="password_confirmation"
                                class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-[#002D72] focus:border-[#002D72] block w-full p-2.5"
                                placeholder="••••••••" required>
                        </div>
                        <div>
                            <label for="role" class="block mb-2 text-sm font-medium text-gray-900">Pilih
                                Role</label>
                            <select id="role" name="role_id"
                                class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-[#002D72] focus:border-[#002D72] block w-full p-2.5">
                                @foreach ($roles as $value => $name)
                                    <option value="{{ $value }}"
                                        {{ old('role_id', 4) == $value ? 'selected' : '' }}>{{ $name }}</option>
                                @endforeach
                            </select>
                            @error('role_id')
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                    <div
                        class="flex items-center justify-end p-5 space-x-3 border-t border-gray-200 rounded-b sticky bottom-0 bg-white">
                        <button @click="showAddModal = false" type="button"
                            class="py-2.5 px-5 text-sm font-medium text-gray-900 bg-white rounded-lg border border-gray-200 hover:bg-gray-100 hover:text-blue-700">Batal</button>
                        <button type="submit"
                            class="text-white bg-gradient-to-r from-[#002D72] to-[#003d8f] hover:from-[#003d8f] font-medium rounded-lg text-sm px-5 py-2.5">Simpan
                            Pengguna</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- ======================== --}}
        {{--      Modal Edit Role     --}}
        {{-- ======================== --}}
        <div x-show="showEditModal" class="fixed inset-0 z-50 flex items-center justify-center p-4" x-cloak>

            {{-- Latar Belakang Transparan & Blur (Sudah Diperbaiki) --}}
            <div x-show="showEditModal" x-transition.opacity class="fixed inset-0 bg-black/50 backdrop-blur-sm"
                @click="showEditModal = false"></div>

            <div x-show="showEditModal" x-transition class="relative bg-white rounded-2xl shadow-xl w-full max-w-lg">
                <div class="flex items-center justify-between p-5 border-b rounded-t">
                    <h3 class="text-xl font-semibold text-gray-900">Edit Role Pengguna</h3>
                    <button type="button" @click="showEditModal = false"
                        class="text-gray-400 bg-transparent hover:bg-gray-200 hover:text-gray-900 rounded-lg text-sm w-8 h-8 ms-auto inline-flex justify-center items-center">
                        <svg class="w-3 h-3" fill="none" viewBox="0 0 14 14">
                            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="m1 1 6 6m0 0 6 6M7 7l6-6M7 7l-6 6" />
                        </svg>
                    </button>
                </div>

                <form :action="formEditAction" method="POST">
                    @csrf @method('PATCH')
                    <div class="p-6 space-y-4">
                        <p class="text-gray-600">Anda akan mengubah role untuk pengguna: <strong
                                x-text="userName"></strong></p>
                        <div>
                            <label for="edit_role" class="block mb-2 text-sm font-medium text-gray-900">Pilih Role
                                Baru</label>
                            <select id="edit_role" name="role_id" x-model="selectedRole"
                                class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-[#002D72] focus:border-[#002D72] block w-full p-2.5">
                                @foreach ($roles as $value => $name)
                                    <option value="{{ $value }}">{{ $name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="flex items-center justify-end p-5 space-x-3 border-t border-gray-200 rounded-b">
                        <button @click="showEditModal = false" type="button"
                            class="py-2.5 px-5 text-sm font-medium text-gray-900 bg-white rounded-lg border border-gray-200 hover:bg-gray-100 hover:text-blue-700">Batal</button>
                        <button type="submit"
                            class="text-white bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-700 font-medium rounded-lg text-sm px-5 py-2.5">Simpan
                            Perubahan</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- ======================== --}}
        {{--    Modal Hapus Pengguna  --}}
        {{-- ======================== --}}
        <div x-show="showDeleteModal" class="fixed inset-0 z-50 flex items-center justify-center p-4" x-cloak>

            {{-- Latar Belakang Transparan & Blur (Sudah Diperbaiki) --}}
            <div x-show="showDeleteModal" x-transition.opacity class="fixed inset-0 bg-black/50 backdrop-blur-sm"
                @click="showDeleteModal = false"></div>

            <div x-show="showDeleteModal" x-transition
                class="relative bg-white rounded-2xl shadow-xl w-full max-w-md">
                <div class="p-6 text-center">
                    <svg class="mx-auto mb-4 text-gray-400 w-12 h-12" fill="none" viewBox="0 0 20 20">
                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M10 11V6m0 8h.01M19 10a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                    <h3 class="mb-5 text-lg font-normal text-gray-500">Anda yakin ingin menghapus pengguna <strong
                            x-text="userName"></strong>? Tindakan ini tidak dapat dibatalkan.</h3>

                    <form :action="formDeleteAction" method="POST" class="inline-block">
                        @csrf @method('DELETE')
                        <button type="submit"
                            class="text-white bg-gradient-to-r from-red-500 to-red-600 hover:from-red-700 font-medium rounded-lg text-sm px-5 py-2.5 me-2">Ya,
                            Hapus</button>
                    </form>
                    <button @click="showDeleteModal = false" type="button"
                        class="py-2.5 px-5 text-sm font-medium text-gray-900 bg-white rounded-lg border border-gray-200 hover:bg-gray-100 hover:text-blue-700">Tidak,
                        Batal</button>
                </div>
            </div>
        </div>

        {{-- ======================== --}}
        {{--       Header Halaman     --}}
        {{-- ======================== --}}
        <div class="mb-6">
            <h1 class="text-3xl font-bold text-gray-900 mb-1">Manajemen Pengguna</h1>
            <p class="text-gray-500">Kelola akun, role, dan hak akses pengguna sistem.</p>
        </div>

        {{-- ======================== --}}
        {{--    Container Berlapis    --}}
        {{-- ======================== --}}
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 mt-4">
            
            {{-- Informasi Jumlah Pengguna --}}
            <div class="mb-5">
                <h2 class="text-xl font-bold text-gray-800">Daftar Pengguna</h2>
                <p class="text-sm text-gray-500 mt-1">Terdapat <span class="font-bold text-[#002D72]">{{ $users->total() }}</span> pengguna terdaftar di dalam sistem.</p>
            </div>

            {{-- Kontrol (Search & Button) --}}
            <div class="flex flex-col sm:flex-row items-center justify-between gap-4 mb-6">
                
                {{-- Search Bar (Dibuat lebih panjang / stretch ke kiri) --}}
                <div class="relative w-full sm:flex-1">
                    <div class="absolute inset-y-0 left-0 flex items-center pl-4 pointer-events-none">
                        <svg class="w-5 h-5 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd"
                                d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z"
                                clip-rule="evenodd" />
                        </svg>
                    </div>
                    <input type="text" x-model="searchQuery" @input="doSearch()"
                        class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-xl focus:ring-[#002D72] focus:border-[#002D72] block w-full pl-11 p-3 transition-shadow"
                        placeholder="Cari pengguna berdasarkan nama atau email...">
                </div>
                
                {{-- Tambah Pengguna (Paling Kanan) --}}
                <div class="w-full sm:w-auto shrink-0">
                    <button type="button" @click="showAddModal = true"
                        class="inline-flex w-full sm:w-auto items-center justify-center gap-2 text-white bg-gradient-to-r from-[#002D72] to-[#003d8f] hover:from-[#003d8f] font-semibold rounded-xl text-sm px-6 py-3 transition-all shadow-sm">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                        </svg>
                        Tambah Pengguna
                    </button>
                </div>
            </div>

            {{-- Notifikasi --}}
            @if (session('success'))
                <div class="mb-6 bg-green-50 border-l-4 border-green-500 text-green-700 p-4 rounded-r-lg text-sm">
                    <p>{{ session('success') }}</p>
                </div>
            @endif
            @if (session('error-modal'))
                <div class="mb-6 bg-red-50 border-l-4 border-red-500 text-red-700 p-4 rounded-r-lg text-sm">
                    <p>{{ session('error-modal') }}</p>
                </div>
            @endif
            @if ($errors->any())
                <div class="mb-6 bg-red-50 border-l-4 border-red-500 text-red-700 p-4 rounded-r-lg text-sm">
                    <p class="font-bold mb-1">Gagal menambahkan pengguna:</p>
                    <ul class="list-disc list-inside">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Tabel Pengguna (Lapisan Dalam / Inner Card) --}}
            <div class="border border-gray-200 rounded-xl overflow-hidden shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left text-gray-500 table-fixed min-w-[800px]">
                    <thead class="bg-gray-100 border-b border-gray-200">
                        <tr>
                            <th class="p-4 text-xs font-bold text-gray-800 uppercase tracking-wider w-[35%]">Pengguna</th>
                            <th class="p-4 text-xs font-bold text-gray-800 uppercase tracking-wider w-[20%]">Role</th>
                            <th class="p-4 text-xs font-bold text-gray-800 uppercase tracking-wider whitespace-nowrap w-[15%]">Tipe Login</th>
                            <th class="p-4 text-xs font-bold text-gray-800 uppercase tracking-wider whitespace-nowrap w-[15%]">Tanggal Bergabung</th>
                            <th class="p-4 text-xs font-bold text-gray-800 uppercase tracking-wider text-right whitespace-nowrap w-[15%]" style="padding-right: 3rem;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($users as $user)
                            <tr class="bg-white border-b hover:bg-gray-50">
                                <td class="px-6 py-4 font-medium text-gray-900 whitespace-nowrap">
                                    <div class="flex items-center space-x-3">
                                        <img class="w-10 h-10 rounded-full object-cover shrink-0"
                                            src="{{ $user->avatarUrl }}" alt="{{ $user->name }} avatar">
                                        <div>
                                            <div class="font-semibold">{{ $user->name }}</div>
                                            <div class="text-sm text-gray-500">{{ $user->email }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @php
                                        $roleName = $roles[$user->role_id] ?? 'Tidak Diketahui';
                                        $roleColor = match ($user->role_id) {
                                            1 => 'bg-blue-100 text-blue-800',
                                            2 => 'bg-purple-100 text-purple-800',
                                            3 => 'bg-yellow-100 text-yellow-800',
                                            default => 'bg-gray-100 text-gray-800',
                                        };
                                    @endphp
                                    <span
                                        class="text-xs font-medium me-2 px-2.5 py-0.5 rounded {{ $roleColor }}">{{ $roleName }}</span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if ($user->google_id)
                                        <span
                                            class="text-xs font-medium me-2 px-2.5 py-0.5 rounded bg-red-100 text-red-800">Google</span>
                                    @else
                                        <span
                                            class="text-xs font-medium me-2 px-2.5 py-0.5 rounded bg-green-100 text-green-800">Email</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">{{ $user->created_at->format('d M Y') }}</td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if ($user->id === Auth::id())
                                        <span class="text-xs text-gray-400 italic">Tidak ada aksi</span>
                                    @else
                                        <div class="flex items-center justify-end gap-2 min-w-max">
                                            <button type="button"
                                                @click="showEditModal = true; userName = '{{ addslashes($user->name) }}'; selectedRole = {{ $user->role_id }}; formEditAction = '{{ route('admin.pengguna.updateRole', $user->id) }}';"
                                                class="inline-flex items-center p-2 text-slate-500 hover:text-[#002D72] bg-white hover:bg-blue-50 border border-slate-200 hover:border-blue-200 rounded-lg transition-colors shadow-sm shrink-0"
                                                title="Edit Pengguna">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg>
                                            </button>
                                            <button type="button"
                                                @click="showDeleteModal = true; userName = '{{ addslashes($user->name) }}'; formDeleteAction = '{{ route('admin.pengguna.destroy', $user->id) }}';"
                                                class="inline-flex items-center p-2 text-red-500 hover:text-red-600 bg-white hover:bg-red-50 border border-slate-200 hover:border-red-200 rounded-lg transition-colors shadow-sm shrink-0"
                                                title="Hapus Pengguna">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                            </button>
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr class="bg-white border-b">
                                <td colspan="5" class="px-6 py-4 text-center text-gray-500">
                                    @if ($search)
                                        Tidak ada pengguna yang cocok dengan pencarian "{{ $search }}".
                                    @else
                                        Belum ada pengguna terdaftar.
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="p-4 border-t border-gray-200 bg-gray-50">{{ $users->links() }}</div>
        </div>
        </div>

    </div>
</x-adminlayout>
