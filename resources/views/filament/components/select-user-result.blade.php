<div class="flex rounded-md relative">
    <div class="flex">
        <div class="flex items-center px-2">
            <div class="h-10 w-10">
                <img src="{{ url('/storage/'.$image.'') }}" alt="{{ $name }}" role="img" class="h-full w-full rounded-full overflow-hidden shadow object-cover" />
            </div>
        </div>
 
        <div class="flex flex-col justify-center pl-3">
            <p class="text-sm font-bold pb-1">{{ $name }}</p>
        </div>
    </div>
</div>