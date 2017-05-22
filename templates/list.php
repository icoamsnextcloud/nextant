<?php /** @var $l \OCP\IL10N */ ?>
<div id='notification'></div>

<div id="emptycontent" class="hidden"></div>

<input type="hidden" name="dir" value="" id="dir">

<div class="nofilterresults hidden">
	<div class="icon-search"></div>
	<h2><?php p($l->t('No entries found in this folder')); ?></h2>
</div>

<table id="filestable">
    <thead>
    <tr>
        <th>ID</th>
        <th>File ID</th>
        <th>Owner</th>
        <th>Path</th>
    </tr>
    </thead>
    <tbody id="exclusionList">
    </tbody>
    <tfoot>
    <td id="exclusionListCount"></td>
    </tfoot>
</table>