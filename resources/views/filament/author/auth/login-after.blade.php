<div class="mt-6 border-t border-gray-200 pt-6 text-center dark:border-white/10">
    <p class="text-sm text-gray-500">
        {{ app()->getLocale() === 'id' ? 'Belum memiliki akun author?' : 'Do not have an author account yet?' }}
    </p>
    <x-filament::button tag="a" color="gray" outlined class="mt-3 w-full" href="{{ route('author.register') }}">
        {{ app()->getLocale() === 'id' ? 'Buat Akun dan Pilih Jalur' : 'Create Account and Choose Path' }}
    </x-filament::button>
</div>
