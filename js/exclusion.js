/**
 * Created by Lawrence Chan on 10/4/2017.
 */
(function() {

    /**
     * @constructs Nextant
     */
    var Nextant = function() {
        this.initialize();
    };

    Nextant.prototype = {

        fileList: null,
        requesting: false,
        currFiles: null,
        oldQuery: '',
        searchResult: null,
        locked: false,
        config: null,

        /**
         * Initialize the file search
         */
        initialize: function () {

            var self = this;

            //
            // sendSearchRequest -
            this.sendRequest = function() {

                var data = {};

                self.postRequest(data);
            };

            //
            // searchRequest - search request
            this.postRequest = function(data) {
                $.post(OC.filePath('nextant', 'ajax', 'exclusion_list.php'), data,
                    self.getRequestResult);
            };

            //
            // searchRequestResult - parse result from last request
            this.getRequestResult = function(infos) {

                var result = infos.result;

                console.log(result);

                $('#exclusionListCount').append(result.length + " file(s)");

                var str = "";

                for (var i = 0; i < result.length; i++) {
                    console.log(result[i]);
                    str += '<tr>';
                    str += '<td>' + result[i].id + '</td>';
                    str += '<td>' + result[i].fileid + '</td>';
                    str += '<td>' + result[i].owner + '</td>';
                    str += '<td>' + result[i].path + '</td>';
                    str += '</tr>';
                }

                $('#exclusionList').append(str);
            }
        }
    };

    nextant = new Nextant();
    $(document).ready(function() {
        nextant.sendRequest();
    });

})();