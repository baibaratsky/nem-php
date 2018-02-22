<?php
/**
 * Part of the evias/nem-php package.
 *
 * NOTICE OF LICENSE
 *
 * Licensed under MIT License.
 *
 * This source file is subject to the MIT License that is
 * bundled with this package in the LICENSE file.
 *
 * @package    evias/nem-php
 * @version    1.0.0
 * @author     Grégory Saive <greg@evias.be>
 * @author     Robin Pedersen (https://github.com/RobertoSnap)
 * @license    MIT License
 * @copyright  (c) 2017-2018, Grégory Saive <greg@evias.be>
 * @link       http://github.com/evias/nem-php
 */
namespace NEM\Models\Transaction;

use NEM\Models\Transaction;
use NEM\Models\TransactionType;
use NEM\Models\Signatures;
use NEM\Models\Transaction\Signature;
use NEM\Models\Fee;

use InvalidArgumentException;

class Multisig
    extends Transaction
{
    /**
     * List of additional fillable attributes
     *
     * @var array
     */
    protected $appends = [
        "innerHash"     => "meta.innerHash",
        "otherTrans"    => "transaction.otherTrans",
        "signatures"    => "transaction.signatures",
    ];

    /**
     * The Multisig transaction type adds offsets `otherTrans` and
     * `signatures`.
     *
     * The `otherTrans` in the DTO is used to store transaction details.
     *
     * The Multisig Transaction Type only acts as a wrapper! It will contain
     * a subordinate Transaction object in the `otherTrans` attribute and a
     * collection of subordinate Transaction\Signature transactions in the
     * `signatures` attribute.
     *
     * @return array
     */
    public function extend() 
    {
        return [
            "otherTrans" => $this->otherTrans()->toDTO("transaction"),
            "signatures" => $this->signatures()->toDTO(),
            // transaction type specialization
            "type" => TransactionType::MULTISIG,
        ];
    }

    /**
     * The extendFee() method must be overloaded by any Transaction Type
     * which needs to extend the base FEE to a custom FEE.
     *
     * @return array
     */
    public function extendFee()
    {
        return Fee::MULTISIG;
    }

    /**
     * The Multisig transaction type adds offsets `innerHash` to the
     * transaction's meta DTO.
     *
     * The `innerHash` attribute references the inner transaction hash.
     *
     * Inner transactions are store in the attribute `otherTrans`.
     *
     * @return array
     */
    public function extendMeta() 
    {
        return [
            "innerHash" => [
                "data" => $this->otherTrans()->hash
            ],
        ];
    }

    /**
     * Setter for the `otherTrans` DTO property.
     *
     * This is used to include non-multisig transaction data in
     * the multisig transaction package.
     *
     * @param   \NEM\Models\Transaction     $otherTrans     Transaction to include in the multisig DTO's `otherTrans` attribute.
     * @return  \NEM\Models\Transaction\Multisig
     */
    public function setOtherTrans(Transaction $otherTrans)
    {
        return $this->otherTrans($otherTrans->toDTO("transaction"));
    }

    /**
     * Mutator for the `otherTrans` sub DTO.
     *
     * @param   array                   $transaction
     * @return  \NEM\Models\Transaction
     */
    public function otherTrans(array $transaction = null)
    {
        // morph Transaction extension - will read the type of transaction
        // and instantiate the correct class extending Transaction.
        $morphed = Transaction::create($transaction ?: $this->getAttribute("otherTrans"));

        if ($morphed->type === TransactionType::MULTISIG) {
            // cannot nest multisig in another multisig.
            throw new InvalidArgumentException("It is forbidden to nest a Multisig transaction in another Multisig transaction.");
        }
        elseif ($morphed->type === TransactionType::MULTISIG_SIGNATURE) {
            // cannot nest multisig in another multisig.
            throw new InvalidArgumentException("It is forbidden to nest a Signature transaction in the inner transaction of a Multisig transaction.");
        }

        return $morphed;
    }

    /**
     * Mutator for the signatures collection.
     *
     * @return \NEM\Models\ModelCollection
     */
    public function signatures(array $signatures = null)
    {
        $transactions = $signatures ?: $this->getAttribute("signatures") ?: [];
        return new Signatures($transactions);
    }
}
