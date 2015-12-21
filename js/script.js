var offset = 0;

$(document).ready(function(){
    $(document).on('click', '#begin', function(){
        $('#begin').attr('disabled', true);
        $('#begin').attr('id', 'status');
        request();
    });
});

function request()
{
    $.get('/req.php', {'offset': offset}, function(data) {
        $('textarea').append(data.log_info + "\n");
        $('#status').html(data.btn_text);
        $('#status').attr('class', 'btn ' + data.btn_bootstrap_class);
        offset = data.offset;

        if (data.end) {
            if (data.filepath) {
                $('#status').attr('disabled', false);
                $('#status').attr('onclick', "location.href='" + data.filepath + "'");
            }
            offset = 0;
        } else {
            request();
        }

        $('textarea').scrollTop($('textarea')[0].scrollHeight);
        
    });
}