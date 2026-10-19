{{-- Just the roster, fetched by app.js when the class or the search box changes. --}}
<x-class-roster :class="$selected" :students="$students" :enrollments="$enrollments"
                :action="$action" :add-label="$addLabel" />
