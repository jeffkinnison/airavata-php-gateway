@if ( isset($expContainer))
    @if (sizeof($expContainer) == 0)
        @if (isset($pageNo) && $pageNo == 1)
            {{ CommonUtilities::print_warning_message('No results found. Please try again.') }}
        @else
            {{ CommonUtilities::print_warning_message('No more results found.') }}
        @endif
    @else
    <div id="re" class="table-responsive">

        <table class="table">
            <tr>
                <th>Name</th>
                <th>Application</th>
                @if( isset($dashboard))
                    <th>User</th>
                @endif
                <!--<th>Description</th>-->
                <th>Resource</th>
                <th>Creation Time</th>
                <th>Status</th>
                @if( isset( $dashboard))
                <th>Check Stats</th>
                @endif
            </tr>

            @foreach($expContainer as $experiment)
                <tr>
                    <!-- Experiment Name -->
                    <td> 
                        <a href="{{URL::to('/')}}/experiment/summary?expId={{$experiment['experiment']->experimentId}}" target="_blank">
                        {{ $experiment['experiment']->name }} 
                        </a>
                        @if( $experiment['expValue']['editable'])
                            <a href="{{URL::to('/')}}/experiment/edit?expId={{$experiment['experiment']->experimentId}}" title="Edit"><span class="glyphicon glyphicon-pencil"></span></a>
                        @endif
                    </td>
                    <!-- Application Name -->
                    @if(isset($experiment['expValue']['applicationInterface']))
                    <td>{{ $experiment['expValue']['applicationInterface']->applicationName }}</td>
                    @else
                    <td></td>
                    @endif
                    <!-- User Names visible to admin -->
                @if( isset($dashboard))
                    <td>{{$experiment['experiment']->userName}}</td>
                @endif
                    <!-- Resource Name -->
                    <td>
                        @if( !empty( explode("_", $experiment['experiment']->resourceHostId)[0] ) ) 
                            {{ explode("_", $experiment['experiment']->resourceHostId)[0] }}
                        @endif
                    </td>

                    <td class="time" unix-time="{{ $experiment['experiment']->creationTime / 1000 }}"></td>

                    <td>
                        <a class="{{ ExperimentUtilities::get_status_color_class( $experiment['expValue']['experimentStatusString'] ) }}" href="{{URL::to('/')}}/experiment/summary?expId={{$experiment['experiment']->experimentId}}" target="_blank">
                            {{$experiment['expValue']['experimentStatusString'] }}
                        </a>
                    </td>
                    @if( isset( $dashboard)) 
                    <td class="text-center">
                        <a class="get-exp-stats" data-expid="{{$experiment['experiment']->experimentId}}" style="cursor: pointer;">
                        <span class="glyphicon glyphicon-stats"></span>
                        </a>
                    </td>
                    @endif
                    
                </tr>
            @endforeach
           
        </table>
    </div>
    @endif
@endif