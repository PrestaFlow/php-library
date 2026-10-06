<?php

namespace PrestaFlow\Library\Exceptions;

/**
 * Le back-office n'a pas répondu dans le plafond donné (issue de la connexion,
 * ou chargement d'une page) : levée par VisualTestsSuite::openBackOfficeCheckpoint().
 * Message lisible, destiné à l'utilisateur.
 */
class BackOfficeTimeoutException extends TimeoutException
{
}
