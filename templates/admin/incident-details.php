<?php
// Example of enhancing the display of incident details
?>
<div class="incident-details">
    <h2>Incident Details</h2>
    <div class="card">
        <div class="card-body">
            <h5 class="card-title"><?php echo esc_html($incident->name); ?></h5>
            <p class="card-text"><strong>Email:</strong> <?php echo esc_html($incident->email); ?></p>
            <p class="card-text"><strong>Type:</strong> <?php echo esc_html($incident->incident_type); ?></p>
            <p class="card-text"><strong>Description:</strong> <?php echo nl2br(esc_html($incident->description)); ?></p>
            <p class="card-text"><strong>Severity:</strong> <?php echo esc_html($incident->severity); ?></p>
            <p class="card-text"><strong>Submitted At:</strong> <?php echo esc_html($incident->submitted_at); ?></p>
        </div>
    </div>
</div>

// Add WYSIWYG editor for notes
<div class="note-section">
    <h3>Add Note</h3>
    <?php
    $content = ''; // Default content for the WYSIWYG editor
    wp_editor($content, 'incident_note', array(
        'textarea_name' => 'incident_note',
        'media_buttons' => true,
        'teeny' => false,
        'textarea_rows' => 5,
    ));
    ?>
</div>
