{{-- resources/views/pengguna/lihatdata.blade.php --}}

<x-penggunalayout title="Lihat Data - pengguna PRANATA">

    <div x-data="{ expandedCategories: new Set() }">
        <div class="space-y-6">
            <div>
                <h1 class="text-2xl md:text-3xl font-bold text-gray-900 mb-1">Direktori Data</h1>
                <p class="text-sm md:text-base text-gray-500">Jelajahi dan lihat detail indikator data statistik yang
                    telah diinput</p>
            </div>

            <div class="space-y-4">
                @forelse($categories as $category)
                    <div
                        class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden hover:shadow-md transition-shadow">
                        <div @click="expandedCategories.has({{ $category->id }}) ? expandedCategories.delete({{ $category->id }}) : expandedCategories.add({{ $category->id }})"
                            class="flex items-center justify-between p-4 md:p-6 cursor-pointer bg-white">

                            <div class="flex items-center gap-4">
                                @php $catName = strtolower($category->name); @endphp

                                @if (Str::contains($catName, ['ekonomi', 'pdrb', 'perdagangan', 'keuangan', 'harga']))
                                    <div
                                        class="w-12 h-12 rounded-lg bg-blue-100 text-blue-600 flex items-center justify-center shrink-0">
                                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                                        </svg>
                                    </div>
                                @elseif(Str::contains($catName, ['sosial', 'demografi', 'penduduk', 'kemiskinan', 'kesehatan']))
                                    <div
                                        class="w-12 h-12 rounded-lg bg-green-100 text-green-600 flex items-center justify-center shrink-0">
                                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                                        </svg>
                                    </div>
                                @elseif(Str::contains($catName, ['lingkungan', 'multi', 'domain', 'pertanian']))
                                    <div
                                        class="w-12 h-12 rounded-lg bg-orange-100 text-orange-600 flex items-center justify-center shrink-0">
                                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9" />
                                        </svg>
                                    </div>
                                @else
                                    <div
                                        class="w-12 h-12 rounded-lg bg-gray-100 text-gray-500 flex items-center justify-center shrink-0">
                                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
                                        </svg>
                                    </div>
                                @endif

                                <div>
                                    <h3 class="font-semibold text-lg md:text-xl text-gray-900">{{ $category->name }}
                                    </h3>
                                    <p class="text-sm text-gray-500">{{ $category->subjects->count() }} Subjek Data</p>
                                </div>
                            </div>

                            <div class="w-8 h-8 rounded-full bg-gray-50 flex items-center justify-center shrink-0">
                                <svg class="w-5 h-5 text-gray-400 transition-transform duration-300"
                                    :class="expandedCategories.has({{ $category->id }}) && 'rotate-180'"
                                    fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M7.41 8.59L12 13.17l4.59-4.58L18 10l-6 6-6-6 1.41-1.41z" />
                                </svg>
                            </div>
                        </div>

                        <div x-show="expandedCategories.has({{ $category->id }})" x-cloak
                            x-transition:enter="transition ease-out duration-200"
                            x-transition:enter-start="opacity-0 -translate-y-2"
                            x-transition:enter-end="opacity-100 translate-y-0"
                            class="bg-gray-50/50 p-4 md:p-6 border-t border-gray-200">

                            @forelse($category->subjects as $subject)
                                <div class="mb-6 last:mb-0">
                                    <div class="flex items-center gap-2.5 mb-3">
                                        <div class="p-1.5 bg-blue-50 text-[#002D72] rounded-lg">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"></path>
                                            </svg>
                                        </div>
                                        <h4 class="text-lg font-bold text-gray-800">{{ $subject->name }}</h4>
                                    </div>

                                    <div class="bg-white rounded-lg border border-gray-200 shadow-sm overflow-hidden">
                                        <div class="overflow-x-auto">
                                            <table class="w-full text-left min-w-[500px]">
                                                <thead class="bg-gray-100 border-b border-gray-200">
                                                    <tr>
                                                        <th
                                                            class="p-4 text-xs font-bold text-gray-800 uppercase tracking-wider">
                                                            Nama Indikator</th>
                                                        <th
                                                            class="p-4 text-xs font-bold text-gray-800 uppercase tracking-wider w-1/4">
                                                            Satuan</th>
                                                        <th
                                                            class="p-4 text-xs font-bold text-gray-800 uppercase tracking-wider text-right w-1/4">
                                                            Tindakan</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="divide-y divide-gray-100">
                                                    @forelse($subject->indicators as $indicator)
                                                        <tr class="hover:bg-blue-50/30 transition-colors">
                                                            <td class="p-4 text-sm font-medium text-gray-800">
                                                                {{ $indicator->name }}
                                                            </td>
                                                            <td class="p-4 text-sm text-gray-500">
                                                                {{ $indicator->unit ?? '-' }}
                                                            </td>
                                                            <td class="p-4 text-right">
                                                                <a href="{{ url('/pengguna/lihatdata/' . $indicator->id) }}"
                                                                    class="inline-flex items-center gap-1.5 px-4 py-2 bg-white text-[#002D72] hover:bg-[#002D72] hover:text-white rounded-lg transition-all text-xs font-bold border border-[#002D72] shadow-sm hover:shadow-md">
                                                                    <svg class="w-4 h-4" fill="none"
                                                                        stroke="currentColor" viewBox="0 0 24 24">
                                                                        <path stroke-linecap="round"
                                                                            stroke-linejoin="round" stroke-width="2"
                                                                            d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                                        <path stroke-linecap="round"
                                                                            stroke-linejoin="round" stroke-width="2"
                                                                            d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                                    </svg>
                                                                    Lihat Data
                                                                </a>
                                                            </td>
                                                        </tr>
                                                    @empty
                                                        <tr>
                                                            <td colspan="3"
                                                                class="p-4 text-sm text-center text-gray-500 italic">
                                                                Belum ada indikator untuk subjek ini.
                                                            </td>
                                                        </tr>
                                                    @endforelse
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            @empty
                                <div class="text-center py-6 text-gray-500">
                                    <p class="text-sm">Belum ada subjek dalam kategori ini.</p>
                                </div>
                            @endforelse
                        </div>
                    </div>
                @empty
                    <div class="text-center py-20 text-gray-500 bg-white rounded-xl shadow-sm border border-gray-200">
                        <svg class="w-12 h-12 mx-auto mb-3 opacity-50" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M5 19a2 2 0 01-2-2V7a2 2 0 012-2h4l2 2h4a2 2 0 012 2v1M5 19h14a2 2 0 002-2v-5a2 2 0 00-2-2H9a2 2 0 00-2 2v5a2 2 0 01-2 2z">
                            </path>
                        </svg>
                        <p class="font-medium text-lg">Belum ada kategori data yang dibuat.</p>
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</x-penggunalayout>
