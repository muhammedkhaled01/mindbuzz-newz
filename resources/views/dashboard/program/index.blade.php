@extends('dashboard.layouts.layout')
@section('content')
    <div class="nk-app-root">
        <div class="nk-main ">
            @include('dashboard.layouts.sidebar')

            <div class="nk-wrap ">
                @include('dashboard.layouts.navbar')

                <div class="nk-content ">
                    <div class="container-fluid">
                        <div class="nk-content-inner">
                            <div class="nk-content-body">
                                <div class="nk-block-head nk-block-head-sm">
                                    <div class="nk-block-between">
                                        <div class="nk-block-head-content">
                                            <h3 class="nk-block-title page-title">Cluster</h3>
                                        </div>
                                        <div class="nk-block-head-content">
                                            <div class="toggle-wrap nk-block-tools-toggle"><a href="#"
                                                    class="btn btn-icon btn-trigger toggle-expand me-n1"
                                                    data-target="more-options"><em class="icon ni ni-more-v"></em></a>
                                                <div class="toggle-expand-content" data-content="more-options">
                                                    <ul class="nk-block-tools g-3">

                                                        <li class="nk-block-tools-opt"><a
                                                                class="btn btn-icon btn-primary d-md-none"
                                                                data-bs-toggle="modal" href="#student-add"><em
                                                                    class="icon ni ni-plus"></em></a>
                                                            <a href="{{ route('programs.create') }}"
                                                                class="btn btn-primary d-none d-md-inline-flex">
                                                                <em class="icon ni ni-plus"></em>
                                                                <span>Add</span>
                                                            </a>
                                                        </li>
                                                    </ul>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th scope="col">#</th>
                                            <th scope="col">Name</th>
                                            <th scope="col">School</th>
                                            <th scope="col">Course</th>
                                            <th scope="col">Action</th>

                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($programs as $programName => $groupedPrograms)
                                            <tr>
                                                <th scope="row">{{ $groupedPrograms->first()->id }}</th>
                                                <td>{{ $programName }}</td>
                                                <td>{{ $groupedPrograms->first()->school->name }}</td>
                                                <td>
                                                    @foreach ($groupedPrograms->take(1) as $program)
                                                        {{ $program->course->name }}/{{ $program->stage->name }}
                                                    @endforeach
                                                    @if ($groupedPrograms->count() > 1)
                                                        <button class="btn btn-link view-more"
                                                            data-program-name="{{ $programName }}">View More</button>
                                                    @endif
                                                    <div class="more-courses d-none"
                                                        data-program-name="{{ $programName }}">
                                                        @foreach ($groupedPrograms->skip(1) as $program)
                                                            {{ $program->course->name }}/{{ $program->stage->name }}<br>
                                                        @endforeach
                                                    </div>
                                                </td>
                                                <td>
                                                    {{-- <a href="{{ route('programs.edit', $groupedPrograms->first()->id) }}"
                                                        class="btn btn-warning me-1">Edit</a> --}}
                                                    @can('program-delete')
                                                        <form
                                                            action="{{ route('programs.destroy', $groupedPrograms->first()->id) }}"
                                                            method="POST" style="display:inline-block;">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="btn btn-danger"
                                                                onclick="return confirm('Are you sure you want to delete this program?')">Delete</button>
                                                        </form>
                                                    @endcan
                                                </td>
                                            </tr>
                                        @endforeach

                                    </tbody>
                                </table>
                                {{-- <div class="mx-auto d-flex justify-content-center">
                                    <div class="nk-block-between-md g-3">
                                        {!! $programs->links() !!}
                                    </div>
                                </div> --}}

                            </div>
                        </div>
                    </div>
                </div>
            </div>
            @include('dashboard.layouts.footer')

        </div>
    </div>
    </div>
@endsection
@section('page_js')
    <script>
        document.querySelectorAll('.view-more').forEach(button => {
            button.addEventListener('click', function() {
                const programName = this.getAttribute('data-program-name');
                const moreCoursesDiv = document.querySelector(
                    `.more-courses[data-program-name="${programName}"]`);
                if (moreCoursesDiv.classList.contains('d-none')) {
                    moreCoursesDiv.classList.remove('d-none');
                    this.textContent = 'View Less';
                } else {
                    moreCoursesDiv.classList.add('d-none');
                    this.textContent = 'View More';
                }
            });
        });
    </script>
@endsection
