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
                                <div class="" role="dialog" id="student-add">
                                    <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
                                        <div class="modal-content"><a href="#" class="close"
                                                data-bs-dismiss="modal"><em class="icon ni ni-cross-sm"></em></a>
                                            <div class="modal-body modal-body-md">
                                                <h5 class="title">Add Students</h5>

                                                <div class="tab-content">
                                                    <div class="tab-pane active" id="student-info">
                                                        <div class="row gy-4">
                                                            <div class="col-md-6">
                                                                <div class="form-group"><label class="form-label"
                                                                        for="full-name">
                                                                        Name</label><input type="text"
                                                                        class="form-control" id="full-name"
                                                                        placeholder="First name"></div>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <div class="form-group"><label class="form-label"
                                                                        for="email">Email
                                                                        Address</label><input type="email"
                                                                        class="form-control" id="email"
                                                                        placeholder="Email Address"></div>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <div class="form-group"><label class="form-label"
                                                                        for="phone-no">Phone
                                                                        Number</label><input type="text"
                                                                        class="form-control" id="phone-no" value="+880"
                                                                        placeholder="Phone Number"></div>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <div class="form-group"><label
                                                                        class="form-label">School</label>
                                                                    <div class="form-control-wrap"><select
                                                                            class="form-select js-select2"
                                                                            data-placeholder="Select multiple options">
                                                                            <option value="default_option">1</option>
                                                                            <option value="option_select_name">2
                                                                            </option>
                                                                            <option value="option_select_name">3
                                                                            </option>

                                                                        </select></div>
                                                                </div>
                                                            </div>


                                                            <div class="col-md-6">
                                                                <div class="form-group"><label
                                                                        class="form-label">Program</label>
                                                                    <div class="form-control-wrap"><select
                                                                            class="form-select js-select2"
                                                                            data-placeholder="Select multiple options">
                                                                            <option value="default_option">1</option>
                                                                            <option value="option_select_name">2
                                                                            </option>
                                                                            <option value="option_select_name">3
                                                                            </option>

                                                                        </select></div>
                                                                </div>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <div class="form-group"><label
                                                                        class="form-label">Grade</label>
                                                                    <div class="form-control-wrap"><select
                                                                            class="form-select js-select2"
                                                                            data-placeholder="Select multiple options">
                                                                            <option value="default_option">1</option>
                                                                            <option value="option_select_name">2
                                                                            </option>
                                                                            <option value="option_select_name">3
                                                                            </option>

                                                                        </select></div>
                                                                </div>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <div class="form-group"><label
                                                                        class="form-label">Class</label>
                                                                    <div class="form-control-wrap"><select
                                                                            class="form-select js-select2"
                                                                            data-placeholder="Select multiple options">
                                                                            <option value="default_option">1</option>
                                                                            <option value="option_select_name">2
                                                                            </option>
                                                                            <option value="option_select_name">3
                                                                            </option>

                                                                        </select></div>
                                                                </div>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <div class="form-group"><label class="form-label"
                                                                        for="phone-no">Password</label>
                                                                    <input type="Password" class="form-control"
                                                                        id="phone-no" value="Password"
                                                                        placeholder="Password">
                                                                </div>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <div class="form-group"><label class="form-label"
                                                                        for="phone-no">Confirm Password</label>
                                                                    <input type="Password" class="form-control"
                                                                        id="phone-no" value="Password"
                                                                        placeholder="Confirm Password">
                                                                </div>
                                                            </div>
                                                            <div class="col-md-12">
                                                                <div class="form-group"><label class="form-label"
                                                                        for="phone-no">Profile Picture</label>
                                                                    <input type="file" id="myFile" name="filename">
                                                                </div>
                                                            </div>
                                                            <div class="col-md-12">
                                                                <ul
                                                                    class="align-center flex-wrap flex-sm-nowrap gx-4 gy-2">
                                                                    <li><a href="#"
                                                                            class="btn btn-primary">create</a>
                                                                    </li>
                                                                    <li><a href="#" class="btn btn-primary">create &
                                                                            create another </a></li>
                                                                    <li><a href="#" data-bs-dismiss="modal"
                                                                            class="link link-light">Cancel</a></li>
                                                                </ul>
                                                            </div>
                                                        </div>
                                                    </div>

                                                </div>
                                            </div>
                                        </div>
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
@endsection
