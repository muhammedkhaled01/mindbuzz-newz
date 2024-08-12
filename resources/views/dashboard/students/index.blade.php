@extends('dashboard.layouts.layout')
@section('content')
<div class="nk-app-root">
    <div class="nk-main">
        @include('dashboard.layouts.sidebar')
        <div class="nk-wrap">
            @include('dashboard.layouts.navbar')
            <div class="nk-content">
                <div class="container-fluid">
                    <div class="nk-content-inner">
                        <div class="nk-content-body">
                            <div class="nk-block-head nk-block-head-sm">
                                <div class="nk-block-between">
                                    <div class="nk-block-head-content">
                                        <h3 class="nk-block-title page-title">Students</h3>
                                    </div>
                                    <div class="nk-block-head-content">
                                        <div class="toggle-wrap nk-block-tools-toggle">
                                            <a href="#" class="btn btn-icon btn-trigger toggle-expand me-n1" data-target="more-options">
                                                <em class="icon ni ni-more-v"></em>
                                            </a>
                                            <div class="toggle-expand-content " data-content="more-options">
                                                <form method="GET" action="{{ route('students.index') }}">
                                                    @csrf
                                                    <ul class="nk-block-tools d-flex justify-content-between">
                                                        <li>
                                                            <div class="drodown">
                                                                <select name="school" class="form-select" onchange="this.form.submit()">
                                                                    <option value="">Select School</option>
                                                                    @foreach ($schools as $school)
                                                                        <option value="{{ $school->id }}" {{ request('school') == $school->id ? 'selected' : '' }}>
                                                                            {{ $school->name }}
                                                                        </option>
                                                                    @endforeach
                                                                </select>
                                                            </div>
                                                        </li>
                                                        <li>
                                                            <div class="drodown">
                                                                <select name="program" class="form-select" onchange="this.form.submit()">
                                                                    <option value="">Select Program</option>
                                                                    @foreach ($programs as $program)
                                                                        <option value="{{ $program->id }}" {{ request('program') == $program->id ? 'selected' : '' }}>
                                                                            {{ $program->name }}
                                                                        </option>
                                                                    @endforeach
                                                                </select>
                                                            </div>
                                                        </li>
                                                        <li>
                                                            <div class="drodown">
                                                                <select name="grade" class="form-select" onchange="this.form.submit()">
                                                                    <option value="">Select Grade</option>
                                                                    @foreach ($grades as $grade)
                                                                        <option value="{{ $grade->id }}" {{ request('grade') == $grade->id ? 'selected' : '' }}>
                                                                            {{ $grade->name }}
                                                                        </option>
                                                                    @endforeach
                                                                </select>
                                                            </div>
                                                        </li>
                                                        <li>
                                                            <div class="drodown">
                                                                <select name="group" class="form-select" onchange="this.form.submit()">
                                                                    <option value="">Select Class</option>
                                                                    @foreach ($classes as $class)
                                                                        <option value="{{ $class->id }}" {{ request('group') == $class->id ? 'selected' : '' }}>
                                                                            {{ $class->sec_name }}
                                                                        </option>
                                                                    @endforeach
                                                                </select>
                                                            </div>
                                                        </li>
                                                        <li class="nk-block-tools-opt">
                                                            <button type="submit" class="btn btn-primary">Filter</button>
                                                        </li>
                                                    </ul>
                                                </form>
                                                
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="nk-block">
                         
                                    <table class="table">
                                             <thead class="thead-dark">
                                                <tr>
                                            <th scope="col">Student</th>
                                            <th scope="col">School</th>
                                            <th scope="col">Phone</th>
                                            <th scope="col">Grade</th>
                                            <th scope="col">Program</th>
                                             <th scope="col" class="text-center">Action</th>
                                            </tr>
                                               </thead>
                                    <tbody>
                                                @foreach ($students as $student)
                                             
                                                            <tr>
                                                            <th scope="row">
                                                          
                                                                <div class="nk-tb-col"><a href="">
                                                                    <div class="user-card">
                                                                        <div class="user-avatar"><img
                                                                                src="../images/avatar/a-sm.jpg"
                                                                                alt="">
                                                                        </div>
                                                                        <div class="user-info"><span
                                                                                class="tb-lead">{{ $student->name }}
                                                                                <span
                                                                                    class="dot dot-warning d-md-none ms-1"></span></span><br><span>{{ $student->email }}</span>
                                                                        </div>
                                                                    </div>
                                                                </a>
                                                            </div>
                                                            </th>
                                                    
                                                      <td>{{ $student->school->name }}</td>
                                                      <td>{{ $student->phone }}</td>
                                                      <td>
                                                            @if (isset($student->details[0]) && isset($student->details[0]->stage))
                                                                <span>{{ $student->details[0]->stage->name }}</span>
                                                            @else
                                                                <span>N/A</span>
                                                            @endif
                                                              </td>
                                                              <td>
                                                            @if (!empty($student->getRoleNames()))
                                                                @foreach ($student->getRoleNames() as $v)
                                                                    <label class="badge badge-secondary text-dark">{{ $v }}</label>
                                                                @endforeach
                                                            @endif
                                                        </td>
                                                        <td class="d-flex flex-row justify-content-center">
                                                    
                                                        <a href="{{ route('students.edit', $student->id) }}"
                                                            class="btn btn-warning me-1">Edit</a>
                                                   
                                                        <form action="{{ route('students.destroy', $student->id) }}"
                                                            method="POST" style="display:inline-block;">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="btn btn-danger"
                                                                onclick="return confirm('Are you sure you want to delete this class?')">Delete</button>

                                                            <div class="d-lg-flex d-none">

                                                            </div>

                                                        </form>
                                                    </td>
                                            </tr>
                                        @endforeach

                                    </tbody>
                                </table>
                                                   
                        
                                       
                                        <div class="card-inner">
                                            <div class="nk-block-between-md g-3">
                                                {!! $students->links() !!}
                                            </div>
                                        </div>
                                
                            </div>
                        </div>
                    </div>
                </div>
                @include('dashboard.layouts.footer')
            </div>
        </div>
    </div>
</div>
@endsection
