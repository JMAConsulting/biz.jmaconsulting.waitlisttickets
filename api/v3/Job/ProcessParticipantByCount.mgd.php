<?php

return [
  [
    'name' => 'ProcessParticipantByCount',
    'entity' => 'Job',
    'params' =>
    [
      'version' => 3,
      'name' => 'Update Participant Statuses by participant count',
      'description' => '',
      'run_frequency' => 'Hourly',
      'api_entity' => 'Job',
      'api_action' => 'process_participant_by_count',
    ],
  ]
];
